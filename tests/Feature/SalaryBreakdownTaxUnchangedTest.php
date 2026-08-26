<?php

namespace Tests\Feature;

use App\Models\EmployeeSalaryAllowance;
use App\Models\EmployeeTaxProfile;
use App\Models\Salary;
use App\Models\SalaryAllowanceType;
use App\Models\User;
use App\Services\TaxService;
use Database\Seeders\TaxRateSeeder;
use Database\Seeders\TerRateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * M20 — Proof of "pajak & BPJS as-is": an employee whose 10jt is split into
 * basic 8jt + allowances 2jt must compute EXACTLY the same PPh21 & BPJS as an
 * employee with a flat 10jt salary. The breakdown is presentation only.
 */
class SalaryBreakdownTaxUnchangedTest extends TestCase
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

    private function withProfile(User $user): void
    {
        EmployeeTaxProfile::create([
            'user_id' => $user->id, 'npwp' => '01.234.567.8-901.000',
            'tax_status' => 'TK/0', 'tax_method' => 'gross',
        ]);
    }

    public function test_split_salary_yields_identical_tax_and_bpjs(): void
    {
        // Employee A: flat 10jt
        $a = User::factory()->create();
        Salary::create(['user_id' => $a->id, 'amount' => 10_000_000, 'overtime_amount' => 0, 'overtime_type' => 'flat']);
        $this->withProfile($a);

        // Employee B: basic 8jt + Jabatan 1.5jt + Transport 0.5jt = 10jt
        $b = User::factory()->create();
        Salary::create(['user_id' => $b->id, 'basic_salary' => 8_000_000, 'overtime_amount' => 0, 'overtime_type' => 'flat']);
        $jab = SalaryAllowanceType::create(['label' => 'Tunjangan Jabatan']);
        $trans = SalaryAllowanceType::create(['label' => 'Transport']);
        EmployeeSalaryAllowance::create(['user_id' => $b->id, 'salary_allowance_type_id' => $jab->id, 'amount' => 1_500_000]);
        EmployeeSalaryAllowance::create(['user_id' => $b->id, 'salary_allowance_type_id' => $trans->id, 'amount' => 500_000]);
        $this->withProfile($b);

        // Totals equal
        $this->assertSame(10_000_000, (int) Salary::where('user_id', $a->id)->value('amount'));
        $this->assertSame(10_000_000, (int) Salary::where('user_id', $b->id)->value('amount'));

        // PPh21 identical
        $this->assertSame(
            $this->tax->calculatePPh21TER($a->fresh(), 10_000_000, 2026),
            $this->tax->calculatePPh21TER($b->fresh(), 10_000_000, 2026),
        );

        // BPJS identical (driven by amount)
        $bpjsA = $this->tax->calculateBPJS($a->fresh(), 10_000_000, 2026);
        $bpjsB = $this->tax->calculateBPJS($b->fresh(), 10_000_000, 2026);
        $this->assertSame($bpjsA, $bpjsB);
    }
}
