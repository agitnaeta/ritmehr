<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\Presence;
use App\Models\User;
use App\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * M22-2 — Portal Camera Check-in.
 *
 * The employee clocks in from their own session with a selfie + geolocation.
 * Identity is always the session user; out-of-radius scans are recorded but
 * flagged pending for a manager. Camera Mode must be active to reach the flow.
 */
class SelfAttendanceCheckinTest extends TestCase
{
    use RefreshDatabase;

    private function guard(): string
    {
        return config('backpack.base.guard', 'backpack');
    }

    private function user(string $name): User
    {
        return User::create([
            'name'     => $name,
            'email'    => str($name)->slug() . uniqid() . '@example.test',
            'password' => bcrypt('secret'),
        ]);
    }

    private function enableCameraMode(): void
    {
        /** @var SettingService $svc */
        $svc = app(SettingService::class);
        $svc->set('attendance_mode', 'camera');
        // Global geofence: office in Jakarta, radius 100m.
        $svc->set('office_lat', '-6.2012');
        $svc->set('office_lng', '106.8169');
        $svc->set('office_radius', '100');
    }

    /** A ~200-byte valid-looking JPEG data URL (passes the controller's checks). */
    private function selfieDataUrl(): string
    {
        return 'data:image/jpeg;base64,' . base64_encode(random_bytes(200));
    }

    public function test_check_in_inside_radius_is_approved_with_selfie(): void
    {
        Storage::fake('local');
        $this->enableCameraMode();
        $user = $this->user('Ahmad Inside');

        $res = $this->actingAs($user, $this->guard())
            ->postJson(route('portal.attendance.checkin.store'), [
                'lat' => -6.2012, 'lng' => 106.8169, 'accuracy' => 8,
                'selfie' => $this->selfieDataUrl(),
            ]);

        $res->assertOk()->assertJson(['ok' => true, 'outside' => false, 'status' => 'approved']);

        $p = Presence::where('user_id', $user->id)->first();
        $this->assertNotNull($p);
        $this->assertEquals('camera', $p->source);
        $this->assertEquals('approved', $p->approval_status);
        $this->assertEquals(0, (int) $p->outside);
        $this->assertNotNull($p->selfie_path);
        Storage::disk('local')->assertExists($p->selfie_path);
    }

    public function test_check_in_outside_radius_is_pending_and_notifies_manager(): void
    {
        Storage::fake('local');
        $this->enableCameraMode();

        $manager = $this->user('Bela Manager');
        Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);
        $manager->assignRole('manager');

        $user = $this->user('Ahmad Faraway');

        // ~11 km away from (0,0) — well outside 100m.
        $res = $this->actingAs($user, $this->guard())
            ->postJson(route('portal.attendance.checkin.store'), [
                'lat' => -6.3012, 'lng' => 106.9169, 'accuracy' => 12,
                'selfie' => $this->selfieDataUrl(),
            ]);

        $res->assertOk()->assertJson(['ok' => true, 'outside' => true, 'status' => 'pending']);

        $p = Presence::where('user_id', $user->id)->first();
        $this->assertEquals('pending', $p->approval_status);
        $this->assertEquals(1, (int) $p->outside);

        // Manager received the pending-approval notification.
        $this->assertDatabaseHas('notifications', [
            'user_id' => $manager->id,
            'type'    => 'attendance_pending_approval',
        ]);
    }

    public function test_second_check_in_same_day_records_checkout(): void
    {
        Storage::fake('local');
        $this->enableCameraMode();
        $user = $this->user('Dewi Twice');

        $payload = ['lat' => -6.2012, 'lng' => 106.8169, 'accuracy' => 8, 'selfie' => $this->selfieDataUrl()];

        $this->actingAs($user, $this->guard())->postJson(route('portal.attendance.checkin.store'), $payload)->assertOk();
        $this->actingAs($user, $this->guard())->postJson(route('portal.attendance.checkin.store'), $payload)->assertOk();

        $p = Presence::where('user_id', $user->id)->first();
        $this->assertNotNull($p->out, 'Second scan should record a checkout time.');
    }

    public function test_selfie_required_rejects_when_missing(): void
    {
        Storage::fake('local');
        $this->enableCameraMode();
        app(SettingService::class)->set('camera_require_selfie', '1');
        $user = $this->user('Eko NoSelfie');

        $res = $this->actingAs($user, $this->guard())
            ->postJson(route('portal.attendance.checkin.store'), [
                'lat' => -6.2012, 'lng' => 106.8169, 'accuracy' => 8,
            ]);

        $res->assertStatus(422);
        $this->assertDatabaseMissing('presences', ['user_id' => $user->id]);
    }

    public function test_camera_mode_guard_blocks_when_qr_mode(): void
    {
        $this->enableCameraMode();
        app(SettingService::class)->set('attendance_mode', 'qr'); // back to QR
        $user = $this->user('Fajar Qr');

        // GET check-in page redirects away with an error.
        $this->actingAs($user, $this->guard())
            ->get(route('portal.attendance.checkin'))
            ->assertRedirect(route('portal.dashboard'));
    }

    public function test_identity_comes_from_session_not_request_body(): void
    {
        Storage::fake('local');
        $this->enableCameraMode();
        $me = $this->user('Gina Me');
        $victim = $this->user('Hana Victim');

        $this->actingAs($me, $this->guard())
            ->postJson(route('portal.attendance.checkin.store'), [
                'lat' => 0.0001, 'lng' => 0.0001, 'accuracy' => 8,
                'selfie' => $this->selfieDataUrl(),
                'user_id' => $victim->id, // spoof attempt — must be ignored
            ])->assertOk();

        $this->assertDatabaseHas('presences', ['user_id' => $me->id, 'source' => 'camera']);
        $this->assertDatabaseMissing('presences', ['user_id' => $victim->id]);
    }

    // ── M22-3 — selfie proof stream authorization ──────────

    private function cameraPresence(User $user): \App\Models\Presence
    {
        Storage::fake('local');
        Storage::disk('local')->put('presences/selfie/x.jpg', 'JPEGBYTES');

        return \App\Models\Presence::create([
            'user_id'     => $user->id,
            'in'          => now()->format('Y-m-d H:i:s'),
            'source'      => 'camera',
            'selfie_path' => 'presences/selfie/x.jpg',
            'lat'         => '-6.2012', 'lng' => '106.8169',
        ]);
    }

    public function test_owner_can_stream_own_selfie(): void
    {
        $this->enableCameraMode();
        $user = $this->user('Owner Selfie');
        $p = $this->cameraPresence($user);

        $this->actingAs($user, $this->guard())
            ->get(route('portal.attendance.selfie', $p->id))
            ->assertOk();
    }

    public function test_other_employee_cannot_stream_someone_elses_selfie(): void
    {
        $this->enableCameraMode();
        $owner = $this->user('Owner Two');
        $stranger = $this->user('Stranger');
        $p = $this->cameraPresence($owner);

        $this->actingAs($stranger, $this->guard())
            ->get(route('portal.attendance.selfie', $p->id))
            ->assertForbidden();
    }

    public function test_presence_viewer_can_stream_any_selfie(): void
    {
        $this->enableCameraMode();
        $owner = $this->user('Owner Three');
        $p = $this->cameraPresence($owner);

        $viewer = $this->user('HR Viewer');
        $perm = \Spatie\Permission\Models\Permission::firstOrCreate(
            ['name' => 'presence.view', 'guard_name' => $this->guard()]
        );
        $viewer->givePermissionTo($perm);

        $this->actingAs($viewer, $this->guard())
            ->get(route('portal.attendance.selfie', $p->id))
            ->assertOk();
    }

    // ── M22-4 — mode-aware portal UI ───────────────────────

    public function test_dashboard_shows_checkin_button_in_camera_mode(): void
    {
        $this->enableCameraMode();
        $user = $this->user('Dash Camera');

        $this->actingAs($user, $this->guard())
            ->get(route('portal.dashboard'))
            ->assertOk()
            ->assertSee('Absen Sekarang')
            ->assertSee(route('portal.attendance.checkin'));
    }

    public function test_dashboard_shows_qr_hint_in_qr_mode(): void
    {
        $this->enableCameraMode();
        app(SettingService::class)->set('attendance_mode', 'qr');
        $user = $this->user('Dash Qr');

        $this->actingAs($user, $this->guard())
            ->get(route('portal.dashboard'))
            ->assertOk()
            ->assertSee('Mode Absensi: QR')
            ->assertDontSee('Absen Sekarang');
    }
}
