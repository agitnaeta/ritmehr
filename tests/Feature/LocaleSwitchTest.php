<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * M13 — Language switching persists to the user + session and drives the UI locale.
 */
class LocaleSwitchTest extends TestCase
{
    use RefreshDatabase;

    public function test_switching_locale_persists_to_user_and_session(): void
    {
        $user = User::create(['name' => 'U', 'email' => 'u@example.test', 'password' => bcrypt('x')]);

        $res = $this->actingAs($user)->get('/locale/en');

        $res->assertRedirect();
        $this->assertSame('en', $user->fresh()->locale);
        $this->assertSame('en', session('locale'));
    }

    public function test_unsupported_locale_is_rejected(): void
    {
        $user = User::create(['name' => 'U', 'email' => 'u2@example.test', 'password' => bcrypt('x')]);

        $this->actingAs($user)->get('/locale/fr')->assertNotFound();
        $this->assertNull($user->fresh()->locale);
    }

    public function test_middleware_applies_user_locale(): void
    {
        $user = User::create(['name' => 'U', 'email' => 'u3@example.test', 'password' => bcrypt('x'), 'locale' => 'en']);

        // Hitting any web route should boot the app in the user's locale.
        $this->actingAs($user)->get('/locale/en');
        $this->assertSame('en', app()->getLocale());
    }

    public function test_menu_translations_exist_both_ways(): void
    {
        app()->setLocale('en');
        $this->assertSame('Accounting', __('menu.accounting'));
        $this->assertSame('Payroll', __('menu.payroll'));

        app()->setLocale('id');
        $this->assertSame('Akuntansi', __('menu.accounting'));
        $this->assertSame('Gajian', __('menu.payroll'));
    }
}
