<?php

namespace Tests\Feature;

use App\Models\Loan;
use App\Models\Notification;
use App\Models\SalaryRecap;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PortalTest extends TestCase
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

    private function actingAsPortal(User $user): self
    {
        $this->actingAs($user, config('backpack.base.guard'));

        return $this;
    }

    private function recapFor(User $user, string $month, int $received = 1_000_000): SalaryRecap
    {
        $recap = SalaryRecap::create([
            'user_id' => $user->id, 'recap_month' => $month, 'work_day' => 20,
            'late_day' => 0, 'salary_amount' => 1_000_000, 'overtime_amount' => 0,
            'loan_cut' => 0, 'late_cut' => 0, 'abstain_cut' => 0,
            'abstain_count' => 0, 'received' => $received,
        ]);

        $recap->received = $received;
        $recap->saveQuietly();

        return $recap;
    }

    // ── Access control ─────────────────────────────────────

    public function test_portal_requires_authentication(): void
    {
        $this->get('/my')->assertRedirect();
    }

    public function test_authenticated_employee_can_open_the_dashboard(): void
    {
        $user = $this->user('Employee');

        $this->actingAsPortal($user)->get('/my')->assertOk()->assertSee('Employee');
    }

    public function test_employee_role_is_kept_out_of_the_admin_panel(): void
    {
        Role::create(['name' => 'employee', 'guard_name' => 'web']);
        $user = $this->user('Just Staff');
        $user->assignRole('employee');

        $this->actingAsPortal($user)
            ->get(backpack_url('dashboard'))
            ->assertRedirect(route('portal.dashboard'));
    }

    public function test_hr_admin_can_still_reach_the_admin_panel(): void
    {
        Role::create(['name' => 'hr_admin', 'guard_name' => 'web']);
        $user = $this->user('HR');
        $user->assignRole('hr_admin');

        $this->actingAsPortal($user)
            ->get(backpack_url('dashboard'))
            ->assertSuccessful();
    }

    public function test_user_without_any_role_keeps_admin_access(): void
    {
        // Legacy accounts predate roles and must not be locked out.
        $user = $this->user('Legacy Admin');

        $this->actingAsPortal($user)
            ->get(backpack_url('dashboard'))
            ->assertSuccessful();
    }

    // ── Data scoping ───────────────────────────────────────

    public function test_salary_list_shows_only_my_own_recaps(): void
    {
        $me = $this->user('Me');
        $other = $this->user('Other');

        $this->recapFor($me, '08-2026', 1_111_111);
        $this->recapFor($other, '08-2026', 9_999_999);

        $this->actingAsPortal($me)
            ->get('/my/salary')
            ->assertOk()
            ->assertSee('1.111.111')
            ->assertDontSee('9.999.999');
    }

    public function test_cannot_open_another_users_payslip(): void
    {
        $me = $this->user('Me');
        $other = $this->user('Other');
        $theirRecap = $this->recapFor($other, '08-2026');

        $this->actingAsPortal($me)
            ->get('/my/salary/' . $theirRecap->id)
            ->assertNotFound();
    }

    public function test_can_open_my_own_payslip(): void
    {
        $me = $this->user('Me');
        $recap = $this->recapFor($me, '08-2026');

        $this->actingAsPortal($me)
            ->get('/my/salary/' . $recap->id)
            ->assertOk()
            ->assertSee('08-2026');
    }

    public function test_loan_page_shows_only_my_loans(): void
    {
        $me = $this->user('Me');
        $other = $this->user('Other');

        Loan::create(['user_id' => $me->id, 'amount' => 500_000, 'date' => now()->toDateString()]);
        Loan::create(['user_id' => $other->id, 'amount' => 7_777_777, 'date' => now()->toDateString()]);

        $this->actingAsPortal($me)
            ->get('/my/loan')
            ->assertOk()
            ->assertSee('500.000')
            ->assertDontSee('7.777.777');
    }

    public function test_cannot_mark_another_users_notification_as_read(): void
    {
        $me = $this->user('Me');
        $other = $this->user('Other');

        $theirs = Notification::create([
            'user_id' => $other->id, 'type' => Notification::LATE_ALERT,
            'title' => 'Theirs', 'body' => 'Not yours', 'channel' => 'database',
        ]);

        $this->actingAsPortal($me)
            ->post('/my/notifications/' . $theirs->id . '/read')
            ->assertNotFound();

        $this->assertNull($theirs->fresh()->read_at);
    }

    // ── Profile ────────────────────────────────────────────

    public function test_profile_update_changes_contact_details_only(): void
    {
        $user = $this->user('Staff', [
            'phone'             => '0800000000',
            'employment_status' => User::STATUS_ACTIVE,
        ]);

        $this->actingAsPortal($user)->post('/my/profile', [
            'email'   => 'new@example.test',
            'phone'   => '08123456789',
            'address' => 'Jl. Baru No. 1',
            // Attempts to escalate — must be ignored.
            'name'              => 'Hacked Name',
            'employment_status' => User::STATUS_TERMINATED,
        ])->assertRedirect();

        $user->refresh();
        $this->assertSame('08123456789', $user->phone);
        $this->assertSame('new@example.test', $user->email);
        $this->assertSame('Staff', $user->name, 'name is HR-controlled');
        $this->assertSame(User::STATUS_ACTIVE, $user->employment_status, 'status is HR-controlled');
    }

    public function test_profile_update_rejects_a_duplicate_email(): void
    {
        $me = $this->user('Me');
        $this->user('Other', ['email' => 'taken@example.test']);

        $this->actingAsPortal($me)
            ->post('/my/profile', ['email' => 'taken@example.test'])
            ->assertSessionHasErrors('email');
    }

    public function test_password_change_requires_the_current_password(): void
    {
        $user = $this->user('Staff');

        $this->actingAsPortal($user)->post('/my/password', [
            'current_password'      => 'wrong-password',
            'password'              => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertSessionHas('error');

        $this->assertTrue(Hash::check('secret', $user->fresh()->password));
    }

    public function test_password_change_succeeds_with_the_right_current_password(): void
    {
        $user = $this->user('Staff');

        $this->actingAsPortal($user)->post('/my/password', [
            'current_password'      => 'secret',
            'password'              => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertSessionHas('success');

        $this->assertTrue(Hash::check('new-password-123', $user->fresh()->password));
    }

    public function test_password_change_requires_confirmation_to_match(): void
    {
        $user = $this->user('Staff');

        $this->actingAsPortal($user)->post('/my/password', [
            'current_password'      => 'secret',
            'password'              => 'new-password-123',
            'password_confirmation' => 'different-password',
        ])->assertSessionHasErrors('password');
    }

    // ── Pages render ───────────────────────────────────────

    public function test_all_portal_pages_render(): void
    {
        $user = $this->user('Staff');

        foreach (['/my', '/my/attendance', '/my/salary', '/my/leave',
                  '/my/leave/create', '/my/loan', '/my/profile',
                  '/my/notifications'] as $url) {
            $this->actingAsPortal($user)->get($url)->assertOk();
        }
    }
}
