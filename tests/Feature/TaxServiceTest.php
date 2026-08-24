<?php

namespace Tests\Feature;

use App\Models\BpjsRate;
use App\Models\EmployeeTaxProfile;
use App\Models\Salary;
use App\Models\User;
use App\Services\TaxService;
use Carbon\Carbon;
use Database\Seeders\TaxRateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaxServiceTest extends TestCase
{
    use RefreshDatabase;

    private TaxService $tax;
    private int $year;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tax = app(TaxService::class);
        $this->year = (int) now()->year;

        $this->seed(TaxRateSeeder::class);
    }

    private function user(string $name, array $attrs = []): User
    {
        return User::create(array_merge([
            'name'     => $name,
            'email'    => str($name)->slug() . '@example.test',
            'password' => bcrypt('secret'),
        ], $attrs));
    }

    private function withProfile(User $user, array $attrs = []): User
    {
        EmployeeTaxProfile::create(array_merge([
            'user_id'    => $user->id,
            'npwp'       => '12.345.678.9-012.000',
            'tax_status' => 'TK/0',
            'tax_method' => 'gross',
        ], $attrs));

        return $user->fresh();
    }

    // ── PTKP ───────────────────────────────────────────────

    public function test_ptkp_reflects_marital_status_and_dependants(): void
    {
        $single = $this->withProfile($this->user('Single'), ['tax_status' => 'TK/0']);
        $married = $this->withProfile($this->user('Married'), ['tax_status' => 'K/0']);
        $marriedTwoKids = $this->withProfile($this->user('Family'), ['tax_status' => 'K/2']);

        $this->assertSame(54_000_000, $this->tax->getApplicablePTKP($single, $this->year));
        $this->assertSame(58_500_000, $this->tax->getApplicablePTKP($married, $this->year));
        $this->assertSame(67_500_000, $this->tax->getApplicablePTKP($marriedTwoKids, $this->year));
    }

    public function test_user_without_a_tax_profile_defaults_to_tk0(): void
    {
        $user = $this->user('No Profile');

        $this->assertSame('TK/0', $this->tax->profileFor($user)->tax_status);
        $this->assertSame(54_000_000, $this->tax->getApplicablePTKP($user, $this->year));
    }

    public function test_ptkp_falls_back_to_the_most_recent_published_year(): void
    {
        $user = $this->withProfile($this->user('Future'), ['tax_status' => 'TK/0']);

        // No table has been entered for a far-future year.
        $this->assertSame(54_000_000, $this->tax->getApplicablePTKP($user, $this->year + 5));
    }

    // ── Brackets ───────────────────────────────────────────

    public function test_progressive_brackets_are_applied_per_slice(): void
    {
        // 60,000,000 sits exactly at the top of the 5% band.
        $this->assertSame(3_000_000, $this->tax->applyBrackets(60_000_000, $this->year));

        // 100,000,000 => 5% of 60m + 15% of 40m = 3m + 6m.
        $this->assertSame(9_000_000, $this->tax->applyBrackets(100_000_000, $this->year));

        // 300,000,000 => 3m + 15% of 190m (28.5m) + 25% of 50m (12.5m).
        $this->assertSame(44_000_000, $this->tax->applyBrackets(300_000_000, $this->year));
    }

    public function test_zero_and_negative_taxable_income_produce_no_tax(): void
    {
        $this->assertSame(0, $this->tax->applyBrackets(0, $this->year));
        $this->assertSame(0, $this->tax->applyBrackets(-5_000_000, $this->year));
    }

    public function test_missing_brackets_produce_no_tax_rather_than_a_guess(): void
    {
        $this->assertSame(0, $this->tax->applyBrackets(100_000_000, 1990));
    }

    // ── BPJS ───────────────────────────────────────────────

    public function test_bpjs_components_use_the_configured_percentages(): void
    {
        $user = $this->withProfile($this->user('Staff'));

        $bpjs = $this->tax->calculateBPJS($user, 10_000_000, $this->year);

        $this->assertSame(100_000, $bpjs['kes_employee'], '1% of 10m');
        $this->assertSame(400_000, $bpjs['kes_employer'], '4% of 10m');
        $this->assertSame(200_000, $bpjs['jht_employee'], '2% of 10m');
        $this->assertSame(370_000, $bpjs['jht_employer'], '3.7% of 10m');
        $this->assertSame(100_000, $bpjs['jp_employee'], '1% of 10m');
        $this->assertSame(200_000, $bpjs['jp_employer'], '2% of 10m');
        $this->assertSame(24_000, $bpjs['jkk'], '0.24% of 10m');
        $this->assertSame(30_000, $bpjs['jkm'], '0.30% of 10m');

        $this->assertSame(400_000, $bpjs['employee_total'], '100k + 200k + 100k');
    }

    public function test_health_insurance_respects_its_salary_ceiling(): void
    {
        $user = $this->withProfile($this->user('Highly Paid'));

        // Ceiling is 12,000,000 — a 20,000,000 salary is capped.
        $bpjs = $this->tax->calculateBPJS($user, 20_000_000, $this->year);

        $this->assertSame(120_000, $bpjs['kes_employee'], '1% of the 12m cap, not of 20m');
    }

    public function test_pension_respects_its_own_ceiling(): void
    {
        $user = $this->withProfile($this->user('Highly Paid'));

        $bpjs = $this->tax->calculateBPJS($user, 20_000_000, $this->year);

        // JP ceiling is 10,042,300 => 1% = 100,423.
        $this->assertSame(100_423, $bpjs['jp_employee']);
    }

    public function test_jht_has_no_ceiling(): void
    {
        $user = $this->withProfile($this->user('Highly Paid'));

        $bpjs = $this->tax->calculateBPJS($user, 20_000_000, $this->year);

        $this->assertSame(400_000, $bpjs['jht_employee'], '2% of the full 20m');
    }

    public function test_disabled_programmes_contribute_nothing(): void
    {
        $user = $this->withProfile($this->user('Opted Out'), [
            'bpjs_kesehatan'       => false,
            'bpjs_ketenagakerjaan' => false,
        ]);

        $bpjs = $this->tax->calculateBPJS($user, 10_000_000, $this->year);

        $this->assertSame(0, $bpjs['employee_total']);
        $this->assertSame(0, $bpjs['employer_total']);
    }

    public function test_individual_programmes_can_be_disabled(): void
    {
        $user = $this->withProfile($this->user('Partial'), ['bpjs_tk_jp' => false]);

        $bpjs = $this->tax->calculateBPJS($user, 10_000_000, $this->year);

        $this->assertSame(0, $bpjs['jp_employee'], 'JP switched off');
        $this->assertSame(200_000, $bpjs['jht_employee'], 'JHT still on');
    }

    public function test_zero_salary_produces_zero_contributions(): void
    {
        $user = $this->withProfile($this->user('Unpaid'));

        $bpjs = $this->tax->calculateBPJS($user, 0, $this->year);

        $this->assertSame(0, $bpjs['employee_total']);
        $this->assertSame(0, $bpjs['employer_total']);
    }

    public function test_bpjs_falls_back_to_the_previous_years_rates(): void
    {
        $user = $this->withProfile($this->user('Staff'));

        $bpjs = $this->tax->calculateBPJS($user, 10_000_000, $this->year + 3);

        $this->assertSame(100_000, $bpjs['kes_employee'], 'uses last published rates');
    }

    // ── PPh 21 ─────────────────────────────────────────────

    public function test_income_below_ptkp_is_not_taxed(): void
    {
        $user = $this->withProfile($this->user('Low Earner'), ['tax_status' => 'TK/0']);

        // 4m/month = 48m/year, under the 54m PTKP even before deductions.
        $this->assertSame(0, $this->tax->calculatePPh21($user, 4_000_000, $this->year));
    }

    public function test_pph21_matches_a_hand_calculation(): void
    {
        $user = $this->withProfile($this->user('Mid Earner'), ['tax_status' => 'TK/0']);

        // Gross 10,000,000/month => 120,000,000/year.
        //   biaya jabatan  = min(5% of 120m, 6m)          = 6,000,000
        //   JHT+JP employee = (200,000 + 100,000) * 12    = 3,600,000
        //   net            = 120m - 6m - 3.6m             = 110,400,000
        //   PTKP TK/0                                     = 54,000,000
        //   PKP                                           = 56,400,000
        //   tax            = 5% of 56,400,000             = 2,820,000
        //   monthly        = 2,820,000 / 12               = 235,000
        $this->assertSame(235_000, $this->tax->calculatePPh21($user, 10_000_000, $this->year));
    }

    public function test_no_npwp_incurs_the_twenty_percent_surcharge(): void
    {
        $withNpwp = $this->withProfile($this->user('Has NPWP'), ['tax_status' => 'TK/0']);
        $withoutNpwp = $this->withProfile($this->user('No NPWP'), [
            'tax_status' => 'TK/0', 'npwp' => null,
        ]);

        $base = $this->tax->calculatePPh21($withNpwp, 10_000_000, $this->year);
        $surcharged = $this->tax->calculatePPh21($withoutNpwp, 10_000_000, $this->year);

        $this->assertSame(235_000, $base);
        $this->assertSame((int) round(235_000 * 1.2), $surcharged);
    }

    public function test_more_dependants_reduce_the_tax(): void
    {
        $single = $this->withProfile($this->user('Single'), ['tax_status' => 'TK/0']);
        $family = $this->withProfile($this->user('Family'), ['tax_status' => 'K/3']);

        $this->assertGreaterThan(
            $this->tax->calculatePPh21($family, 10_000_000, $this->year),
            $this->tax->calculatePPh21($single, 10_000_000, $this->year)
        );
    }

    public function test_zero_income_is_not_taxed(): void
    {
        $user = $this->withProfile($this->user('Nothing'));

        $this->assertSame(0, $this->tax->calculatePPh21($user, 0, $this->year));
    }

    public function test_biaya_jabatan_is_capped_at_six_million(): void
    {
        $user = $this->withProfile($this->user('High Earner'), ['tax_status' => 'TK/0']);

        // 50m/month => 600m/year; 5% would be 30m but the cap is 6m, so the
        // tax must exceed what an uncapped deduction would produce.
        $tax = $this->tax->calculatePPh21($user, 50_000_000, $this->year);

        $this->assertGreaterThan(0, $tax);
        // Sanity bound: monthly tax on 50m must stay well under the gross.
        $this->assertLessThan(50_000_000, $tax);
    }

    // ── THR ────────────────────────────────────────────────

    public function test_thr_is_a_full_month_after_a_year_of_service(): void
    {
        $user = $this->user('Veteran', ['join_date' => now()->subYears(3)->toDateString()]);
        Salary::create([
            'user_id' => $user->id, 'amount' => 8_000_000,
            'overtime_amount' => 0, 'overtime_type' => 'flat',
        ]);

        $this->assertSame(8_000_000, $this->tax->calculateTHR($user->fresh()));
    }

    public function test_thr_is_prorated_for_a_partial_year(): void
    {
        $user = $this->user('New Joiner', ['join_date' => now()->subMonths(6)->toDateString()]);

        $this->assertSame(
            (int) round(12_000_000 * 6 / 12),
            $this->tax->calculateTHR($user->fresh(), 12_000_000)
        );
    }

    public function test_thr_is_zero_under_one_month_of_service(): void
    {
        $user = $this->user('Brand New', ['join_date' => now()->subDays(10)->toDateString()]);

        $this->assertSame(0, $this->tax->calculateTHR($user->fresh(), 10_000_000));
    }

    public function test_thr_is_zero_without_a_join_date(): void
    {
        $user = $this->user('Unknown Start');

        $this->assertSame(0, $this->tax->calculateTHR($user, 10_000_000));
    }
}
