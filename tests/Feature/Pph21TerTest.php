<?php

namespace Tests\Feature;

use App\Models\EmployeeTaxProfile;
use App\Models\User;
use App\Services\TaxService;
use Database\Seeders\TerRateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * M19-3/M19-4 — TER category mapping + monthly TER withholding (Jan–Nov).
 */
class Pph21TerTest extends TestCase
{
    use RefreshDatabase;

    private TaxService $tax;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tax = app(TaxService::class);
        $this->seed(TerRateSeeder::class);
    }

    private function employee(string $status, bool $npwp = true): User
    {
        $user = User::factory()->create();
        EmployeeTaxProfile::create([
            'user_id'    => $user->id,
            'npwp'       => $npwp ? '01.234.567.8-901.000' : null,
            'tax_status' => $status,
            'tax_method' => 'gross',
        ]);
        return $user->fresh();
    }

    // ── category mapping ───────────────────────────────────

    public function test_category_mapping_covers_all_statuses(): void
    {
        $expect = [
            'TK/0' => 'A', 'TK/1' => 'A', 'K/0' => 'A',
            'TK/2' => 'B', 'TK/3' => 'B', 'K/1' => 'B', 'K/2' => 'B',
            'K/3'  => 'C',
            'K/I/0' => 'A', 'K/I/1' => 'B', 'K/I/2' => 'B', 'K/I/3' => 'C',
        ];
        foreach ($expect as $status => $cat) {
            $this->assertSame($cat, $this->tax->terCategory($status), "status {$status}");
        }
    }

    public function test_unknown_status_defaults_to_a(): void
    {
        $this->assertSame('A', $this->tax->terCategory('ZZ/9'));
    }

    // ── Simulasi 1: TK/0, 10jt, ber-NPWP → 2% = 200.000 ────

    public function test_simulasi_1_tk0_10jt_npwp(): void
    {
        $user = $this->employee('TK/0', npwp: true);
        $pph = $this->tax->calculatePPh21TER($user, 10_000_000, 2026);
        $this->assertSame(200_000, $pph);
    }

    // ── Simulasi 3: TK/0, 5jt (below floor) → 0 ────────────

    public function test_simulasi_3_below_floor_is_zero(): void
    {
        $user = $this->employee('TK/0', npwp: true);
        $this->assertSame(0, $this->tax->calculatePPh21TER($user, 5_000_000, 2026));
    }

    // ── No-NPWP surcharge 20% on the TER amount ────────────

    public function test_no_npwp_surcharge_applies(): void
    {
        $user = $this->employee('TK/0', npwp: false);
        // 10jt A = 2% = 200.000, ×1.20 = 240.000
        $this->assertSame(240_000, $this->tax->calculatePPh21TER($user, 10_000_000, 2026));
    }

    // ── Zero / negative gross → 0 ──────────────────────────

    public function test_zero_gross_is_zero(): void
    {
        $user = $this->employee('TK/0');
        $this->assertSame(0, $this->tax->calculatePPh21TER($user, 0, 2026));
    }

    // ── No table for the year → 0 (never invents a rate) ───

    public function test_absent_year_yields_zero(): void
    {
        $user = $this->employee('TK/0');
        $this->assertSame(0, $this->tax->calculatePPh21TER($user, 10_000_000, 1999));
    }

    // ── Category B floor differs (K/2, 6jt below B floor) ──

    public function test_category_b_floor(): void
    {
        $user = $this->employee('K/2', npwp: true);
        // B floor is 6.2jt; 6jt → 0%
        $this->assertSame(0, $this->tax->calculatePPh21TER($user, 6_000_000, 2026));
    }
}
