<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * M08 — Reporting & Dashboard polish.
 *
 * Verifies the two real gaps found auditing against code:
 *  1) the /admin/report/* group is now gated by permission:report.view
 *     (previously any authenticated user could open the reports);
 *  2) the dashboard money figures follow the currency setting (money())
 *     instead of a hard-coded "Rp".
 */
class ReportingDashboardTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $name, array $attrs = []): User
    {
        return User::create(array_merge([
            'name'     => $name,
            'email'    => str($name)->slug() . '@example.test',
            'password' => bcrypt('secret'),
        ], $attrs));
    }

    private function guard(): string
    {
        return config('backpack.base.guard', 'backpack');
    }

    /**
     * A user holding exactly the given permissions, assigned directly (no role).
     *
     * CheckIfAdmin treats a role-less account as an admin (this app predates
     * roles), so the request reaches the permission middleware — letting us
     * test permission:report.view in isolation without coupling to role names.
     */
    private function userWith(array $permissions): User
    {
        $guard = $this->guard();
        $perms = collect($permissions)->map(fn ($p) =>
            Permission::firstOrCreate(['name' => $p, 'guard_name' => $guard])
        );

        $user = $this->user('User ' . uniqid());
        $user->givePermissionTo($perms);

        return $user;
    }

    // ── Route guard ────────────────────────────────────────

    /** @dataProvider reportRoutes */
    public function test_report_routes_require_report_view_permission(string $path): void
    {
        // An employee-like user WITHOUT report.view must be blocked.
        $blocked = $this->userWith(['presence.view']);

        $resp = $this->actingAs($blocked, $this->guard())->get(backpack_url($path));
        $this->assertSame(403, $resp->status(), "$path should be forbidden without report.view");
    }

    /** @dataProvider reportRoutes */
    public function test_report_routes_open_with_report_view_permission(string $path): void
    {
        $allowed = $this->userWith(['report.view']);

        $this->actingAs($allowed, $this->guard())
            ->get(backpack_url($path))
            ->assertOk();
    }

    public static function reportRoutes(): array
    {
        return [
            'attendance' => ['report/attendance'],
            'salary'     => ['report/salary'],
            'loan'       => ['report/loan'],
            'headcount'  => ['report/headcount'],
        ];
    }

    public function test_guest_is_redirected_from_reports(): void
    {
        $this->get(backpack_url('report/attendance'))->assertRedirect();
    }

    // ── Dashboard currency (E7) ────────────────────────────

    public function test_dashboard_money_follows_currency_setting(): void
    {
        $admin = $this->userWith(
            ['report.view', 'salary.view', 'salary_recap.view']
        );

        $settings = app(SettingService::class);

        // Default IDR → "Rp" prefix rendered by money().
        $settings->set('default_currency', 'IDR');
        $settings->flush();

        $this->actingAs($admin, $this->guard())
            ->get(backpack_url('dashboard'))
            ->assertOk()
            ->assertSee('Rp');

        // Switch to USD → dashboard must reflect the new symbol, proving it
        // reads the setting rather than a hard-coded "Rp".
        $settings->set('default_currency', 'USD');
        $settings->flush();

        $html = $this->actingAs($admin, $this->guard())
            ->get(backpack_url('dashboard'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('$', $html, 'dashboard should show the USD symbol');

        // reset for other tests
        $settings->set('default_currency', 'IDR');
        $settings->flush();
    }

    // ── Onboarding empty-state (QW-04) ─────────────────────

    /**
     * Instance baru (hanya 1 user admin, belum ada struktur gaji) harus
     * menampilkan kartu panduan "Mulai di sini" di dashboard.
     */
    public function test_dashboard_shows_onboarding_card_on_empty_instance(): void
    {
        $admin = $this->userWith(['report.view']);

        $this->actingAs($admin, $this->guard())
            ->get(backpack_url('dashboard'))
            ->assertOk()
            ->assertSee('Mulai di sini')
            ->assertSee('Lengkapi Profil Perusahaan')
            ->assertSee('Atur Struktur Gaji');
    }

    /**
     * Setelah ada karyawan + struktur gaji, kartu onboarding harus hilang.
     */
    public function test_dashboard_hides_onboarding_card_when_data_exists(): void
    {
        $admin = $this->userWith(['report.view']);

        // Tambah karyawan kedua + struktur gaji supaya needsOnboarding = false.
        $employee = $this->user('Karyawan Satu');
        \App\Models\Salary::create([
            'user_id'         => $employee->id,
            'basic_salary'    => 5_000_000,
            'amount'          => 5_000_000,
            'overtime_amount' => 0,
            'overtime_type'   => 'flat',
        ]);

        $this->actingAs($admin, $this->guard())
            ->get(backpack_url('dashboard'))
            ->assertOk()
            ->assertDontSee('Mulai di sini');
    }
}
