<?php

namespace Tests\Feature;

use App\Models\Presence;
use App\Models\User;
use App\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * M22-1 — Self-Attendance foundation (data + settings toggle).
 *
 * Proves the additive schema and the QR/Camera mode setting exist and behave:
 *  - new presence columns are fillable and default sanely (source=qr,
 *    approval_status=approved) so existing QR records stay valid;
 *  - attendance_mode + camera_require_selfie are defined in the 'lokasi' group
 *    and round-trip through SettingService.
 */
class SelfAttendanceFoundationTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $name): User
    {
        return User::create([
            'name'     => $name,
            'email'    => str($name)->slug() . '@example.test',
            'password' => bcrypt('secret'),
        ]);
    }

    public function test_presence_defaults_keep_existing_records_valid(): void
    {
        $user = $this->user('Ahmad Foundation');

        // A bare presence (like the QR flow creates) must default to a valid,
        // approved QR record — no NULL surprises on the new NOT-NULL columns.
        $p = Presence::create([
            'user_id' => $user->id,
            'in'      => now()->format('Y-m-d H:i:s'),
        ]);

        $this->assertEquals('qr', $p->fresh()->source);
        $this->assertEquals('approved', $p->fresh()->approval_status);
        $this->assertNull($p->fresh()->selfie_path);
    }

    public function test_camera_columns_are_fillable(): void
    {
        $user = $this->user('Dewi Camera');

        $p = Presence::create([
            'user_id'         => $user->id,
            'in'              => now()->format('Y-m-d H:i:s'),
            'source'          => 'camera',
            'selfie_path'     => 'presences/selfie/abc.jpg',
            'accuracy'        => 8.5,
            'lat'             => '-6.2012',
            'lng'             => '106.8169',
            'approval_status' => 'pending',
            'approval_note'   => 'di luar radius',
            'approved_by'     => null,
        ])->fresh();

        $this->assertEquals('camera', $p->source);
        $this->assertEquals('presences/selfie/abc.jpg', $p->selfie_path);
        $this->assertEquals('8.50', (string) $p->accuracy);
        $this->assertEquals('pending', $p->approval_status);
        $this->assertEquals('di luar radius', $p->approval_note);
    }

    public function test_selfie_url_is_null_without_a_path(): void
    {
        $user = $this->user('Budi NoSelfie');
        $p = Presence::create(['user_id' => $user->id, 'in' => now()->format('Y-m-d H:i:s')]);

        $this->assertNull($p->selfieUrl());
    }

    public function test_attendance_mode_setting_is_defined_and_defaults_to_qr(): void
    {
        $defs = SettingService::definitions();

        $this->assertArrayHasKey('attendance_mode', $defs);
        $this->assertEquals('lokasi', $defs['attendance_mode']['group']);
        $this->assertEquals('select', $defs['attendance_mode']['type']);
        $this->assertArrayHasKey('qr', $defs['attendance_mode']['options']);
        $this->assertArrayHasKey('camera', $defs['attendance_mode']['options']);

        $this->assertArrayHasKey('camera_require_selfie', $defs);
        $this->assertEquals('bool', $defs['camera_require_selfie']['type']);

        // With no stored row, callers fall back to 'qr' (back-compat default).
        $this->assertEquals('qr', setting('attendance_mode', 'qr'));
    }

    public function test_attendance_mode_setting_round_trips(): void
    {
        /** @var SettingService $svc */
        $svc = app(SettingService::class);
        $svc->set('attendance_mode', 'camera');

        $this->assertEquals('camera', setting('attendance_mode', 'qr'));
    }
}
