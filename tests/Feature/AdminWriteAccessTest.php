<?php

namespace Tests\Feature;

use App\Models\Loan;
use App\Models\Salary;
use App\Models\Schedule;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * CWE-862 — a role that may only VIEW a module must not be able to write to it.
 *
 * Route middleware only gates `.view`; writes are gated in the controller
 * (denyAccess, or hasAccessOrFail in an overridden store/update). Every case
 * here is one HTTP request in its own test: Backpack's CrudPanel singleton
 * only runs setupXxxOperation() for the first request of a test.
 */
class AdminWriteAccessTest extends TestCase
{
    use RefreshDatabase;

    /** View-only permissions an operator might add to a read-only role. */
    private const EXTRA_VIEW_PERMISSIONS = [
        'tax.view', 'document.view', 'national_holiday.view',
        'company_profile.view', 'branch.view',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function userWithRole(string $role, string $name): User
    {
        $user = User::create([
            'name'     => $name,
            'email'    => str($name)->slug() . '@example.test',
            'password' => Hash::make('original-secret'),
        ]);
        $user->assignRole($role);

        return $user;
    }

    /** The seeded, read-only `manager` role — default configuration. */
    private function manager(): User
    {
        return $this->userWithRole('manager', 'Manager');
    }

    /** A manager an operator has also allowed to VIEW tax/documents/etc. */
    private function readOnlyAuditor(): User
    {
        $user = $this->manager();
        $user->givePermissionTo(self::EXTRA_VIEW_PERMISSIONS);

        return $user;
    }

    /**
     * Log in on the backpack guard WITHOUT actingAs(): actingAs() also calls
     * shouldUse() and makes `backpack` the default guard, so Spatie would look
     * the seeded `web`-guard roles up under the wrong guard. In production the
     * default guard stays `web`, which is what this reproduces.
     */
    private function send(User $user, string $method, string $path, array $data = [])
    {
        auth()->guard(config('backpack.base.guard'))->setUser($user);

        return $this->call($method, backpack_url($path), $data);
    }

    // ── Default config: the seeded manager role ────────────

    public static function managerWrites(): array
    {
        return [
            // Reported: route gate only, no controller write gate.
            'day store'               => ['POST',   'day', ['name' => 'X']],
            'day update'              => ['PUT',    'day/1', ['id' => 1, 'name' => 'X']],
            'day delete'              => ['DELETE', 'day/1'],
            'day-off store'           => ['POST',   'schedule-day-off', ['name' => 'X']],
            'day-off update'          => ['PUT',    'schedule-day-off/1', ['id' => 1]],
            'day-off delete'          => ['DELETE', 'schedule-day-off/1'],
            // Not reported: denyAccess is set, but an overridden store()/update()
            // never calls hasAccessOrFail(), so the denial is never enforced.
            'loan store'              => ['POST', 'loan', ['amount' => 1, 'date' => '2026-01-01']],
            'loan update'             => ['PUT',  'loan/1', ['id' => 1, 'amount' => 1, 'date' => '2026-01-01']],
            'loan-payment store'      => ['POST', 'loan-payment', ['amount' => 1, 'date' => '2026-01-01']],
            'loan-payment update'     => ['PUT',  'loan-payment/1', ['id' => 1, 'amount' => 1]],
            'presence store'          => ['POST', 'presence', []],
            'presence update'         => ['PUT',  'presence/1', ['id' => 1]],
            'salary store'            => ['POST', 'salary', []],
            'salary-recap store'      => ['POST', 'salary-recap', []],
            'salary-recap update'     => ['PUT',  'salary-recap/1', ['id' => 1]],
            'schedule store'          => ['POST', 'schedule', []],
            'user update'             => ['PUT',  'user/1', ['id' => 1]],
            // Custom route outside Backpack's operations — needs its own check.
            'schedule mass-update'    => ['POST', 'schedule/mass-update', ['user_ids' => [1], 'schedule_ids' => [1]]],
            'schedule mass-update form' => ['GET', 'schedule/view-update'],
        ];
    }

    #[DataProvider('managerWrites')]
    public function test_read_only_manager_cannot_write(string $method, string $path, array $data = []): void
    {
        $this->send($this->manager(), $method, $path, $data)->assertForbidden();
    }

    public function test_read_only_manager_cannot_take_over_another_account(): void
    {
        $admin = $this->userWithRole('super_admin', 'Boss');
        $manager = $this->manager();

        $this->send($manager, 'PUT', "user/{$admin->id}", [
            'id'       => $admin->id,
            'name'     => 'Boss',
            'email'    => $admin->email,
            'password' => 'attacker-chosen',
            'password_confirmation' => 'attacker-chosen',
        ])->assertForbidden();

        $this->assertTrue(Hash::check('original-secret', $admin->fresh()->password));
    }

    public function test_read_only_manager_cannot_issue_a_loan(): void
    {
        $staff = $this->userWithRole('employee', 'Staff');

        $this->send($this->manager(), 'POST', 'loan', [
            'user_id' => $staff->id, 'amount' => 5_000_000, 'date' => now()->toDateString(),
        ])->assertForbidden();

        $this->assertSame(0, Loan::count());
    }

    // These two load the entry during operation setup, so they need a real
    // record to get as far as the access check.
    public function test_read_only_manager_cannot_update_a_salary(): void
    {
        $staff = $this->userWithRole('employee', 'Staff');
        $salary = Salary::create(['user_id' => $staff->id, 'basic_salary' => 5_000_000, 'overtime_amount' => 0, 'overtime_type' => 'flat']);

        $this->send($this->manager(), 'PUT', "salary/{$salary->id}", [
            'id' => $salary->id, 'user_id' => $staff->id, 'basic_salary' => 99_000_000,
        ])->assertForbidden();

        $this->assertSame(5_000_000, $salary->fresh()->basic_salary);
    }

    public function test_read_only_manager_cannot_update_a_schedule(): void
    {
        $schedule = Schedule::create([
            'name' => 'Reguler', 'in' => '08:00:00', 'out' => '17:00:00',
            'over_in' => '18:00:00', 'over_out' => '22:00:00',
        ]);

        $this->send($this->manager(), 'PUT', "schedule/{$schedule->id}", [
            'id' => $schedule->id, 'name' => 'Hijacked', 'in' => '10:00:00',
        ])->assertForbidden();

        $this->assertSame('Reguler', $schedule->fresh()->name);
    }

    public function test_read_only_manager_can_still_view_working_days(): void
    {
        $this->send($this->manager(), 'GET', 'day')->assertOk();
    }

    // ── View-only role created by an operator ──────────────

    public static function viewOnlyWrites(): array
    {
        $cases = [];
        foreach (['tax-profile', 'ptkp-rate', 'pph21-bracket', 'ter-rate', 'bpjs-rate',
                  'document-type', 'national-holiday', 'company-profile', 'branch'] as $crud) {
            $cases["$crud create form"] = ['GET',    "$crud/create"];
            $cases["$crud store"]       = ['POST',   $crud, ['name' => 'X']];
            $cases["$crud update"]      = ['PUT',    "$crud/1", ['id' => 1]];
            $cases["$crud delete"]      = ['DELETE', "$crud/1"];
        }

        $cases['tax recalculate']        = ['POST', 'tax-report/recalculate', ['month' => '01-2026']];
        $cases['employee-document form'] = ['GET',  'employee-document/create'];
        $cases['employee-document store']  = ['POST', 'employee-document', []];
        $cases['employee-document delete'] = ['POST', 'employee-document/1/delete'];

        return $cases;
    }

    #[DataProvider('viewOnlyWrites')]
    public function test_view_only_role_cannot_write(string $method, string $path, array $data = []): void
    {
        $this->send($this->readOnlyAuditor(), $method, $path, $data)->assertForbidden();
    }

    public static function viewOnlyReads(): array
    {
        return array_map(fn ($p) => [$p], [
            'tax-profile' => 'tax-profile', 'ptkp-rate' => 'ptkp-rate', 'pph21-bracket' => 'pph21-bracket',
            'ter-rate' => 'ter-rate', 'bpjs-rate' => 'bpjs-rate', 'document-type' => 'document-type',
            'national-holiday' => 'national-holiday', 'company-profile' => 'company-profile',
            'branch' => 'branch', 'employee-document' => 'employee-document',
        ]);
    }

    #[DataProvider('viewOnlyReads')]
    public function test_view_only_role_can_still_view(string $path): void
    {
        $this->send($this->readOnlyAuditor(), 'GET', $path)->assertOk();
    }

    public static function writeButtons(): array
    {
        return [
            'document upload'   => ['employee-document', 'employee-document/create'],
            'tax recalculate'   => ['tax-report/bpjs', 'tax-report/recalculate'],
            'day add (backpack)' => ['day', 'day/create'],
        ];
    }

    #[DataProvider('writeButtons')]
    public function test_view_only_role_does_not_see_write_buttons(string $page, string $writeUrl): void
    {
        $this->send($this->readOnlyAuditor(), 'GET', $page)
            ->assertOk()
            ->assertDontSee(backpack_url($writeUrl), false);
    }

    #[DataProvider('writeButtons')]
    public function test_hr_admin_sees_write_buttons(string $page, string $writeUrl): void
    {
        $this->send($this->userWithRole('hr_admin', 'HR'), 'GET', $page)
            ->assertOk()
            ->assertSee(backpack_url($writeUrl), false);
    }

    // ── Editors keep write access ──────────────────────────

    public static function editorForms(): array
    {
        return array_map(fn ($p) => [$p], [
            'day' => 'day/create', 'schedule-day-off' => 'schedule-day-off/create',
            'tax-profile' => 'tax-profile/create', 'ptkp-rate' => 'ptkp-rate/create',
            'pph21-bracket' => 'pph21-bracket/create', 'ter-rate' => 'ter-rate/create',
            'bpjs-rate' => 'bpjs-rate/create', 'document-type' => 'document-type/create',
            'national-holiday' => 'national-holiday/create', 'company-profile' => 'company-profile/create',
            'branch' => 'branch/create', 'employee-document' => 'employee-document/create',
            'loan' => 'loan/create', 'schedule' => 'schedule/create',
            'schedule mass-update' => 'schedule/view-update',
        ]);
    }

    #[DataProvider('editorForms')]
    public function test_hr_admin_can_still_open_write_forms(string $path): void
    {
        $this->send($this->userWithRole('hr_admin', 'HR'), 'GET', $path)->assertOk();
    }
}
