<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Notifications\WahaAdminService;
use App\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * M03b — In-app WhatsApp (WAHA) connection: service status mapping + admin
 * routes are super-admin only and proxy to WAHA correctly.
 */
class WahaConnectionTest extends TestCase
{
    use RefreshDatabase;

    private function configureWaha(): void
    {
        $s = app(SettingService::class);
        $s->set('whatsapp_enabled', true);
        $s->set('waha_url', 'http://waha:3000');
        $s->set('waha_session', 'default');
        $s->set('waha_api_key', 'secret');
        $s->flush();
    }

    private function superAdmin(): User
    {
        $role = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'backpack']);
        $user = User::create(['name' => 'Boss', 'email' => 'boss@ex.test', 'password' => bcrypt('x')]);
        $user->assignRole($role);

        return $user;
    }

    // ── Service ────────────────────────────────────────────

    public function test_status_maps_working_state_and_account(): void
    {
        $this->configureWaha();
        Http::fake(['*/api/sessions/default' => Http::response([
            'status' => 'WORKING',
            'me'     => ['id' => '628123@c.us', 'pushName' => 'HRD'],
        ], 200)]);

        $status = WahaAdminService::fromSettings()->status();

        $this->assertTrue($status['reachable']);
        $this->assertSame('WORKING', $status['state']);
        $this->assertTrue($status['connected']);
        $this->assertSame('HRD', $status['me']['pushName']);
    }

    public function test_status_maps_scan_qr_state(): void
    {
        $this->configureWaha();
        Http::fake(['*/api/sessions/default' => Http::response(['status' => 'SCAN_QR_CODE'], 200)]);

        $status = WahaAdminService::fromSettings()->status();

        $this->assertSame('SCAN_QR_CODE', $status['state']);
        $this->assertFalse($status['connected']);
    }

    public function test_status_handles_missing_session_as_stopped(): void
    {
        $this->configureWaha();
        Http::fake(['*/api/sessions/default' => Http::response(['error' => 'not found'], 404)]);

        $status = WahaAdminService::fromSettings()->status();

        $this->assertTrue($status['reachable']);
        $this->assertSame('STOPPED', $status['state']);
    }

    public function test_start_sends_api_key_header(): void
    {
        $this->configureWaha();
        Http::fake(['*' => Http::response([], 200)]);

        $ok = WahaAdminService::fromSettings()->start();

        $this->assertTrue($ok);
        Http::assertSent(fn ($req) => str_contains($req->url(), '/api/sessions/default/start')
            && $req->hasHeader('X-Api-Key', 'secret'));
    }

    public function test_logout_calls_waha(): void
    {
        $this->configureWaha();
        Http::fake(['*/api/sessions/default/logout' => Http::response([], 200)]);

        $this->assertTrue(WahaAdminService::fromSettings()->logout());
    }

    public function test_qr_requests_image_and_returns_png_bytes(): void
    {
        $this->configureWaha();
        Http::fake(['*/api/default/auth/qr*' => Http::response('PNGBYTES', 200, ['Content-Type' => 'image/png'])]);

        $qr = WahaAdminService::fromSettings()->qr();

        $this->assertNotNull($qr);
        $this->assertSame('image/png', $qr['contentType']);
        $this->assertSame('PNGBYTES', $qr['body']);
        // Must ask WAHA for an image explicitly (Accept header beats ?format).
        Http::assertSent(fn ($req) => str_contains($req->url(), '/api/default/auth/qr')
            && $req->hasHeader('Accept', 'image/png'));
    }

    public function test_qr_returns_null_when_waha_responds_json(): void
    {
        $this->configureWaha();
        // Session not in SCAN state → WAHA returns JSON, not an image.
        Http::fake(['*/api/default/auth/qr*' => Http::response(['error' => 'not scanning'], 200, ['Content-Type' => 'application/json'])]);

        $this->assertNull(WahaAdminService::fromSettings()->qr());
    }

    public function test_from_settings_null_when_url_missing(): void
    {
        $s = app(SettingService::class);
        $s->set('waha_url', '');
        $s->flush();

        $this->assertNull(WahaAdminService::fromSettings());
    }

    // ── Controller / routes ────────────────────────────────

    public function test_status_endpoint_requires_super_admin(): void
    {
        $user = User::create(['name' => 'Staff', 'email' => 'staff@ex.test', 'password' => bcrypt('x')]);

        $this->actingAs($user, 'backpack')
            ->get(backpack_url('whatsapp/status'))
            ->assertForbidden();
    }

    public function test_status_endpoint_returns_not_configured_json(): void
    {
        $s = app(SettingService::class);
        $s->set('waha_url', '');
        $s->flush();

        $this->actingAs($this->superAdmin(), 'backpack')
            ->get(backpack_url('whatsapp/status'))
            ->assertOk()
            ->assertJson(['state' => 'NOT_CONFIGURED', 'connected' => false]);
    }

    public function test_index_page_loads_for_super_admin(): void
    {
        $this->configureWaha();

        $this->actingAs($this->superAdmin(), 'backpack')
            ->get(backpack_url('whatsapp'))
            ->assertOk()
            ->assertSee('Koneksi WhatsApp');
    }

    public function test_start_endpoint_proxies_to_waha(): void
    {
        $this->configureWaha();
        Http::fake(['*' => Http::response([], 200)]);

        $this->actingAs($this->superAdmin(), 'backpack')
            ->post(backpack_url('whatsapp/start'))
            ->assertOk()
            ->assertJson(['ok' => true]);

        Http::assertSent(fn ($req) => str_contains($req->url(), '/api/sessions/default/start'));
    }
}
