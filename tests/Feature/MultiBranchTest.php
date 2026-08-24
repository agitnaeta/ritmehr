<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Presence;
use App\Models\User;
use App\Services\PresenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MultiBranchTest extends TestCase
{
    use RefreshDatabase;

    private PresenceService $presences;

    /** Monas, Jakarta. */
    private const JAKARTA_LAT = -6.1753924;
    private const JAKARTA_LNG = 106.8271528;

    /** Gedung Sate, Bandung — ~120 km away. */
    private const BANDUNG_LAT = -6.9024812;
    private const BANDUNG_LNG = 107.6186178;

    protected function setUp(): void
    {
        parent::setUp();
        $this->presences = app(PresenceService::class);
    }

    private function branch(string $name, ?float $lat, ?float $lng, int $radius = 100): Branch
    {
        return Branch::create([
            'name'          => $name,
            'code'          => strtoupper(substr(md5($name), 0, 5)),
            'lat'           => $lat,
            'lng'           => $lng,
            'radius_meters' => $radius,
            'is_active'     => true,
        ]);
    }

    private function user(string $name, ?int $branchId = null): User
    {
        return User::create([
            'name'      => $name,
            'email'     => str($name)->slug() . '@example.test',
            'password'  => bcrypt('secret'),
            'branch_id' => $branchId,
        ]);
    }

    // ── Distance and containment ───────────────────────────

    public function test_distance_between_jakarta_and_bandung_is_realistic(): void
    {
        $jakarta = $this->branch('Jakarta', self::JAKARTA_LAT, self::JAKARTA_LNG);

        $distance = $jakarta->distanceTo(self::BANDUNG_LAT, self::BANDUNG_LNG);

        // Straight-line Jakarta–Bandung is roughly 120 km.
        $this->assertGreaterThan(100_000, $distance);
        $this->assertLessThan(150_000, $distance);
    }

    public function test_a_point_at_the_branch_itself_is_inside(): void
    {
        $branch = $this->branch('Jakarta', self::JAKARTA_LAT, self::JAKARTA_LNG);

        $this->assertTrue($branch->contains(self::JAKARTA_LAT, self::JAKARTA_LNG));
    }

    public function test_radius_is_respected_per_branch(): void
    {
        $tight = $this->branch('Tight', self::JAKARTA_LAT, self::JAKARTA_LNG, 50);
        $loose = $this->branch('Loose', self::JAKARTA_LAT, self::JAKARTA_LNG, 5000);

        // ~1 km north of the office (0.009 degrees latitude).
        $lat = self::JAKARTA_LAT + 0.009;

        $this->assertFalse($tight->contains($lat, self::JAKARTA_LNG), '1 km is outside a 50 m fence');
        $this->assertTrue($loose->contains($lat, self::JAKARTA_LNG), '1 km is inside a 5 km fence');
    }

    public function test_branch_without_coordinates_has_no_geofence(): void
    {
        $branch = $this->branch('Unmapped', null, null);

        $this->assertFalse($branch->hasGeofence());
        $this->assertNull($branch->distanceTo(self::JAKARTA_LAT, self::JAKARTA_LNG));
        $this->assertFalse($branch->contains(self::JAKARTA_LAT, self::JAKARTA_LNG));
    }

    // ── Presence integration ───────────────────────────────

    public function test_scan_at_own_branch_is_recorded_as_inside(): void
    {
        $branch = $this->branch('Jakarta', self::JAKARTA_LAT, self::JAKARTA_LNG, 200);
        $user = $this->user('Jakarta Staff', $branch->id);

        $presence = Presence::create(['user_id' => $user->id, 'in' => now()]);
        $this->presences->updateCoordinate($presence, self::JAKARTA_LAT, self::JAKARTA_LNG);

        $presence->refresh();
        $this->assertFalse((bool) $presence->outside);
        $this->assertSame($branch->id, $presence->branch_id);
    }

    public function test_scan_at_another_branch_is_flagged_outside(): void
    {
        $jakarta = $this->branch('Jakarta', self::JAKARTA_LAT, self::JAKARTA_LNG, 200);
        $this->branch('Bandung', self::BANDUNG_LAT, self::BANDUNG_LNG, 200);
        $user = $this->user('Jakarta Staff', $jakarta->id);

        $presence = Presence::create(['user_id' => $user->id, 'in' => now()]);
        // Scanning from Bandung while assigned to Jakarta.
        $this->presences->updateCoordinate($presence, self::BANDUNG_LAT, self::BANDUNG_LNG);

        $this->assertTrue((bool) $presence->fresh()->outside);
    }

    public function test_each_branch_judges_its_own_staff_independently(): void
    {
        $jakarta = $this->branch('Jakarta', self::JAKARTA_LAT, self::JAKARTA_LNG, 200);
        $bandung = $this->branch('Bandung', self::BANDUNG_LAT, self::BANDUNG_LNG, 200);

        $jktStaff = $this->user('JKT', $jakarta->id);
        $bdgStaff = $this->user('BDG', $bandung->id);

        $jktPresence = Presence::create(['user_id' => $jktStaff->id, 'in' => now()]);
        $bdgPresence = Presence::create(['user_id' => $bdgStaff->id, 'in' => now()]);

        // Both scan at their own office.
        $this->presences->updateCoordinate($jktPresence, self::JAKARTA_LAT, self::JAKARTA_LNG);
        $this->presences->updateCoordinate($bdgPresence, self::BANDUNG_LAT, self::BANDUNG_LNG);

        $this->assertFalse((bool) $jktPresence->fresh()->outside);
        $this->assertFalse((bool) $bdgPresence->fresh()->outside);
    }

    public function test_recalculation_keeps_the_branch_originally_recorded(): void
    {
        $jakarta = $this->branch('Jakarta', self::JAKARTA_LAT, self::JAKARTA_LNG, 200);
        $bandung = $this->branch('Bandung', self::BANDUNG_LAT, self::BANDUNG_LNG, 200);
        $user = $this->user('Transferred', $jakarta->id);

        $presence = Presence::create(['user_id' => $user->id, 'in' => now()]);
        $this->presences->updateCoordinate($presence, self::JAKARTA_LAT, self::JAKARTA_LNG);

        // The employee later transfers to Bandung.
        $user->update(['branch_id' => $bandung->id]);

        $this->presences->recalCulateCoordinate($presence->fresh());

        $presence->refresh();
        $this->assertSame(
            $jakarta->id,
            $presence->branch_id,
            'history stays attributed to the branch where the scan happened'
        );
        $this->assertFalse((bool) $presence->outside);
    }

    public function test_user_without_a_branch_falls_back_to_global_config(): void
    {
        config([
            'app.office_lat'    => self::JAKARTA_LAT,
            'app.office_lng'    => self::JAKARTA_LNG,
            'app.office_radius' => 150,
        ]);

        $user = $this->user('No Branch');
        $presence = Presence::create(['user_id' => $user->id, 'in' => now()]);

        $this->presences->updateCoordinate($presence, self::JAKARTA_LAT, self::JAKARTA_LNG);
        $this->assertFalse((bool) $presence->fresh()->outside);

        $far = Presence::create(['user_id' => $user->id, 'in' => now()]);
        $this->presences->updateCoordinate($far, self::BANDUNG_LAT, self::BANDUNG_LNG);
        $this->assertTrue((bool) $far->fresh()->outside);
    }

    public function test_no_geofence_configured_anywhere_does_not_flag_everyone(): void
    {
        config(['app.office_lat' => 0, 'app.office_lng' => 0]);

        $user = $this->user('Unfenced');
        $presence = Presence::create(['user_id' => $user->id, 'in' => now()]);

        $this->presences->updateCoordinate($presence, self::BANDUNG_LAT, self::BANDUNG_LNG);

        $this->assertFalse(
            (bool) $presence->fresh()->outside,
            'with no reference point there is nothing to be outside of'
        );
    }

    public function test_branch_deletion_leaves_users_intact(): void
    {
        $branch = $this->branch('Closing', self::JAKARTA_LAT, self::JAKARTA_LNG);
        $user = $this->user('Staff', $branch->id);

        $branch->delete();

        $this->assertDatabaseHas('users', ['id' => $user->id, 'branch_id' => null]);
    }
}
