<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * M03 — the "Kirim Tes" WhatsApp endpoint: guarded to super admin, and actually
 * hits the configured gateway.
 */
class TestWhatsAppEndpointTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        $role = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'backpack']);
        $user = User::create(['name' => 'Boss', 'email' => 'boss@ex.test', 'password' => bcrypt('x')]);
        $user->assignRole($role);

        return $user;
    }

    public function test_super_admin_can_send_test_and_it_hits_waha(): void
    {
        Http::fake(['*/api/sendText' => Http::response(['id' => 'x'], 201)]);

        $s = app(SettingService::class);
        $s->set('whatsapp_enabled', true);
        $s->set('waha_url', 'http://waha:3000');
        $s->flush();

        $res = $this->actingAs($this->superAdmin(), 'backpack')
            ->post(backpack_url('settings/test-whatsapp'), ['phone' => '08123456789']);

        $res->assertRedirect();
        Http::assertSent(fn ($req) => str_contains($req->url(), '/api/sendText')
            && $req['chatId'] === '628123456789@c.us');
    }

    public function test_phone_is_required(): void
    {
        $res = $this->actingAs($this->superAdmin(), 'backpack')
            ->post(backpack_url('settings/test-whatsapp'), ['phone' => '']);

        $res->assertSessionHasErrors('phone');
    }

    public function test_non_super_admin_is_forbidden(): void
    {
        $user = User::create(['name' => 'Staff', 'email' => 'staff@ex.test', 'password' => bcrypt('x')]);

        $res = $this->actingAs($user, 'backpack')
            ->post(backpack_url('settings/test-whatsapp'), ['phone' => '0812']);

        $res->assertForbidden();
    }
}
