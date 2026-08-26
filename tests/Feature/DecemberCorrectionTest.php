<?php

namespace Tests\Feature;

use App\Models\EmployeeTaxProfile;
use App\Models\Salary;
use App\Models\SalaryRecap;
use App\Models\User;
use App\Services\TaxService;
use Database\Seeders\TaxRateSeeder;
use Database\Seeders\TerRateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * M19-5 — December year-end reconciliation + Jan–Nov/Dec routing in applyToRecap.
 *
 * Simulasi 1 (§ plan): TK/0, NPWP, flat Rp 10,000,000/month, BPJS off.
 *   Jan–Nov: TER A 2% = Rp 200,000 × 11 = Rp 2,200,000
 *   December: annual progressive Rp 3,000,000 − Rp 2,200,000 = Rp 800,000
 *   Total year = Rp 3,000,000 (equals pure progressive — TER only smooths cashflow)
 */
class DecemberCorrectionTest extends TestCase
{
    use RefreshDatabase;

    private TaxService $tax;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tax = app(TaxService::class);
        $this->seed(TaxRateSeeder::class);
        $this->seed(TerRateSeeder::class);
    }

    private function employee(string $status = 'TK/0', bool $npwp = true): User
    {
        $user = User::factory()->create();
        // No BPJS deductions → clean numbers matching the hand-calc.
        Salary::create(['user_id' => $user->id, 'amount' => 10_000_000, 'overtime_amount' => 0]);
        EmployeeTaxProfile::create([
            'user_id' => $user->id,
            'npwp' => $npwp ? '01.234.567.8-901.000' : null,
            'tax_status' => $status,
            'tax_method' => 'gross',
            'bpjs_kesehatan' => false,
            'bpjs_ketenagakerjaan' => false,
            'bpjs_tk_jht' => false, 'bpjs_tk_jp' => false,
            'bpjs_tk_jkk' => false, 'bpjs_tk_jkm' => false,
        ]);
        return $user->fresh();
    }

    private function recap(User $user, int $monthNum, int $salary = 10_000_000): SalaryRecap
    {
        // Build without firing SalaryRecapObserver (which runs the full salary
        // recalc needing schedule/presence). We test the tax logic directly.
        $recap = new SalaryRecap([
            'user_id' => $user->id,
            'recap_month' => sprintf('%02d-2026', $monthNum),
            'work_day' => 22, 'late_day' => 0,
            'salary_amount' => $salary, 'overtime_amount' => 0,
            'loan_cut' => 0, 'late_cut' => 0, 'abstain_cut' => 0,
            'abstain_count' => 0, 'received' => 0,
        ]);
        $recap->saveQuietly();

        return $this->tax->applyToRecap($recap->fresh());
    }

    public function test_jan_to_nov_uses_ter_200k_each(): void
    {
        $user = $this->employee();
        foreach (range(1, 11) as $m) {
            $recap = $this->recap($user, $m);
            $this->assertSame(200_000, (int) $recap->pph21, "month {$m} should be TER 200k");
        }
    }

    public function test_december_reconciles_to_800k_and_year_totals_3m(): void
    {
        $user = $this->employee();

        // Generate Jan–Nov first so December can read accumulated figures.
        foreach (range(1, 11) as $m) {
            $this->recap($user, $m);
        }

        $december = $this->recap($user, 12);

        $this->assertSame(800_000, (int) $december->pph21, 'December correction');

        $yearTotal = (int) SalaryRecap::where('user_id', $user->id)
            ->where('recap_month', 'like', '%-2026')
            ->sum('pph21');
        $this->assertSame(3_000_000, $yearTotal, 'full-year PPh 21 equals progressive');
    }

    public function test_routing_month_11_is_ter_month_12_is_correction(): void
    {
        $user = $this->employee();
        $nov = $this->recap($user, 11);
        $this->assertSame(200_000, (int) $nov->pph21); // TER path

        $dec = $this->recap($user, 12);
        // With only Nov present (200k withheld), correction = 3,000,000 − 200,000
        // but annual gross here is Nov+Dec only = 20,000,000 → below PTKP → tax 0
        // → correction refunds the 200k already taken.
        $this->assertSame(-200_000, (int) $dec->pph21, 'refund when annual gross below PTKP');
    }

    public function test_no_npwp_year_total_is_progressive_times_1_2(): void
    {
        $user = $this->employee(npwp: false);
        foreach (range(1, 12) as $m) {
            $this->recap($user, $m);
        }
        $yearTotal = (int) SalaryRecap::where('user_id', $user->id)
            ->where('recap_month', 'like', '%-2026')
            ->sum('pph21');

        // Progressive Rp 3,000,000 × 1.20 = Rp 3,600,000
        $this->assertSame(3_600_000, $yearTotal);
    }
}
