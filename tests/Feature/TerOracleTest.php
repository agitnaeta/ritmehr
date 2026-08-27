<?php

namespace Tests\Feature;

use App\Models\EmployeeTaxProfile;
use App\Models\User;
use App\Services\TaxService;
use Database\Seeders\TerRateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * M19 — TER oracle: rupiah-exact expected withholding for a matrix of
 * {status, gross, npwp}. These lock the calculation so a future refactor (or a
 * corrected rate table) surfaces any drift immediately.
 *
 * ⚠️ Expected values assume the DRAFT PMK 168/2023 table in
 * database/data/ter_rates_2026.csv. When the client verifies the official
 * rates, update the CSV AND these expectations together.
 */
class TerOracleTest extends TestCase
{
    use RefreshDatabase;

    private TaxService $tax;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tax = app(TaxService::class);
        $this->seed(TerRateSeeder::class);
    }

    private function employee(string $status, bool $npwp): User
    {
        $user = User::factory()->create();
        EmployeeTaxProfile::create([
            'user_id' => $user->id,
            'npwp' => $npwp ? '01.234.567.8-901.000' : null,
            'tax_status' => $status,
            'tax_method' => 'gross',
        ]);
        return $user->fresh();
    }

    #[DataProvider('oracleProvider')]
    public function test_ter_monthly_matches_oracle(string $status, int $gross, bool $npwp, int $expected): void
    {
        $user = $this->employee($status, $npwp);
        $this->assertSame(
            $expected,
            $this->tax->calculatePPh21TER($user, $gross, 2026),
            "TER {$status} gross={$gross} npwp=" . ($npwp ? 'Y' : 'N')
        );
    }

    /** @return array<string, array{0:string,1:int,2:bool,3:int}> */
    public static function oracleProvider(): array
    {
        return [
            // Category A (TK/0): floor 5.4jt = 0%
            'A TK/0 5jt below floor'   => ['TK/0', 5_000_000, true, 0],
            'A TK/0 10jt = 2%'         => ['TK/0', 10_000_000, true, 200_000],
            'A TK/0 10jt no-npwp +20%' => ['TK/0', 10_000_000, false, 240_000],
            'A K/0 same as TK/0'       => ['K/0', 10_000_000, true, 200_000],
            // 12.5jt–13.75jt = 5.00%
            'A TK/0 13jt = 5%'         => ['TK/0', 13_000_000, true, 650_000],
            // 24.15jt–26.45jt = 10.00%
            'A TK/0 25jt = 10%'        => ['TK/0', 25_000_000, true, 2_500_000],

            // Category B (TK/2, K/1, K/2): floor 6.2jt = 0%
            'B K/2 6jt below floor'    => ['K/2', 6_000_000, true, 0],
            // 7.3jt–9.2jt = 1.00%
            'B K/2 8jt = 1%'           => ['K/2', 8_000_000, true, 80_000],
            'B TK/2 8jt = 1%'          => ['TK/2', 8_000_000, true, 80_000],

            // Category C (K/3): floor 6.6jt = 0%
            'C K/3 6.5jt below floor'  => ['K/3', 6_500_000, true, 0],
            // 7.8jt–8.85jt = 1.00%
            'C K/3 8jt = 1%'           => ['K/3', 8_000_000, true, 80_000],
        ];
    }
}
