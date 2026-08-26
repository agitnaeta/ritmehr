<?php

namespace Tests\Feature;

use App\Models\EmployeeTaxProfile;
use App\Models\Salary;
use App\Models\SalaryRecap;
use App\Models\Schedule;
use App\Models\User;
use App\Services\SalaryService;
use Database\Seeders\TaxRateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * M05 — Tax auto-calculation.
 *
 * The gap this closes: PPh21/BPJS/net_income used to be written only by a
 * manual "recalculate tax" button. Here we prove that the ordinary salary
 * recalculation flow (what the SalaryRecapObserver calls) now populates the
 * statutory figures on its own — no manual applyToRecap().
 */
class SalaryTaxAutoCalcTest extends TestCase
{
    use RefreshDatabase;

    private SalaryService $salary;
    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(TaxRateSeeder::class);
        $this->salary = app(SalaryService::class);

        $schedule = Schedule::create([
            'name' => 'Reguler', 'in' => '08:00:00', 'out' => '17:00:00',
            'over_in' => '18:00:00', 'over_out' => '22:00:00',
        ]);

        $this->staff = User::create([
            'name'        => 'Karyawan Pajak',
            'email'       => 'pajak@example.test',
            'password'    => bcrypt('secret'),
            'schedule_id' => $schedule->id,
        ]);

        Salary::create([
            'user_id'                => $this->staff->id,
            'amount'                 => 10_000_000,
            'overtime_amount'        => 50_000,
            'overtime_type'          => 'flat',
            'unpaid_leave_deduction' => 100_000,
            'fine_type'              => 'flat',
            'fine'                   => 0,
            'fine_per_minute'        => 0,
        ]);

        EmployeeTaxProfile::create([
            'user_id'    => $this->staff->id,
            'npwp'       => '12.345.678.9-012.000',
            'tax_status' => 'TK/0',
            'tax_method' => 'gross',
        ]);
    }

    private function makeRecap(): SalaryRecap
    {
        $month = now()->format('m-Y');

        $recap = SalaryRecap::create([
            'user_id'         => $this->staff->id,
            'recap_month'     => $month,
            'work_day'        => 22,
            'late_day'        => 0,
            'salary_amount'   => 10_000_000,
            'overtime_amount' => 0,
            'loan_cut'        => 0,
            'late_cut'        => 0,
            'abstain_cut'     => 0,
            'abstain_count'   => 0,
            'received'        => 0,
        ]);

        // Blank the statutory fields so we can prove the recalc fills them.
        $recap->forceFill([
            'pph21' => 0, 'bpjs_kes_employee' => 0, 'bpjs_jht_employee' => 0,
            'bpjs_jp_employee' => 0, 'gross_income' => 0, 'net_income' => 0,
        ])->saveQuietly();

        return $recap->fresh();
    }

    public function test_recalculation_populates_tax_and_bpjs_without_manual_step(): void
    {
        $recap = $this->makeRecap();

        // The ordinary flow — exactly what SalaryRecapObserver triggers.
        $this->salary->calculateSalaryRecap($recap);

        $fresh = $recap->fresh();

        $this->assertGreaterThan(0, $fresh->gross_income, 'gross harus terisi');
        $this->assertGreaterThan(0, $fresh->pph21, 'PPh21 harus dihitung otomatis');
        $this->assertGreaterThan(0, $fresh->bpjs_kes_employee, 'BPJS kesehatan harus terisi');
        $this->assertGreaterThan(0, $fresh->bpjs_jht_employee, 'BPJS JHT harus terisi');
        $this->assertGreaterThan(0, $fresh->net_income, 'net_income harus terisi');
    }

    public function test_net_income_equals_gross_minus_all_employee_deductions(): void
    {
        $recap = $this->makeRecap();

        $this->salary->calculateSalaryRecap($recap);
        $fresh = $recap->fresh();

        $expectedNet = (int) $fresh->gross_income
            - ((int) $fresh->loan_cut + (int) $fresh->late_cut + (int) $fresh->abstain_cut)
            - ((int) $fresh->bpjs_kes_employee + (int) $fresh->bpjs_jht_employee + (int) $fresh->bpjs_jp_employee)
            - (int) $fresh->pph21;

        $this->assertSame(max(0, $expectedNet), (int) $fresh->net_income);
    }

    public function test_recalculation_is_idempotent(): void
    {
        $recap = $this->makeRecap();

        $this->salary->calculateSalaryRecap($recap);
        $first = $recap->fresh()->only(['gross_income', 'pph21', 'net_income', 'bpjs_jht_employee']);

        $this->salary->calculateSalaryRecap($recap->fresh());
        $second = $recap->fresh()->only(['gross_income', 'pph21', 'net_income', 'bpjs_jht_employee']);

        $this->assertSame($first, $second, 'hitung ulang tidak boleh mengubah hasil pajak');
    }
}
