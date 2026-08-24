<?php

namespace Database\Seeders;

use App\Models\BpjsRate;
use App\Models\Pph21Bracket;
use App\Models\PtkpRate;
use Illuminate\Database\Seeder;

/**
 * Statutory Indonesian rates.
 *
 * PTKP (PMK 101/2016) and the PPh 21 brackets (UU HPP No. 7/2021) below are
 * the figures in force at the time of writing. Verify against the current
 * regulations before running payroll — these change by ministerial decree and
 * the tables are year-keyed precisely so they can be updated without code.
 */
class TaxRateSeeder extends Seeder
{
    public function run(): void
    {
        // Seeds the current year; use the admin CRUD to add other years.
        $year = (int) now()->year;

        $this->seedPtkp($year);
        $this->seedBrackets($year);
        $this->seedBpjs($year);

        $this->command?->info("Seeded PTKP, PPh 21 brackets and BPJS rates for {$year}.");
    }

    /**
     * PTKP per year. Base TK/0 = 54,000,000; +4,500,000 for marriage and for
     * each dependant (max 3). K/I means the spouse's income is combined, which
     * adds the spouse's own 54,000,000.
     */
    private function seedPtkp(int $year): void
    {
        $base = 54_000_000;
        $increment = 4_500_000;

        $rates = [];

        for ($dependants = 0; $dependants <= 3; $dependants++) {
            $rates["TK/{$dependants}"] = $base + ($increment * $dependants);
            $rates["K/{$dependants}"] = $base + $increment + ($increment * $dependants);
            $rates["K/I/{$dependants}"] = $base + $increment + $base + ($increment * $dependants);
        }

        foreach ($rates as $status => $amount) {
            PtkpRate::updateOrCreate(
                ['year' => $year, 'status' => $status],
                ['amount' => $amount]
            );
        }
    }

    /**
     * Progressive PPh 21 brackets under UU HPP (five tiers).
     */
    private function seedBrackets(int $year): void
    {
        $brackets = [
            ['lower_bound' => 0,             'upper_bound' => 60_000_000,    'rate' => 5.00],
            ['lower_bound' => 60_000_000,    'upper_bound' => 250_000_000,   'rate' => 15.00],
            ['lower_bound' => 250_000_000,   'upper_bound' => 500_000_000,   'rate' => 25.00],
            ['lower_bound' => 500_000_000,   'upper_bound' => 5_000_000_000, 'rate' => 30.00],
            ['lower_bound' => 5_000_000_000, 'upper_bound' => null,          'rate' => 35.00],
        ];

        foreach ($brackets as $bracket) {
            Pph21Bracket::updateOrCreate(
                ['year' => $year, 'lower_bound' => $bracket['lower_bound']],
                ['upper_bound' => $bracket['upper_bound'], 'rate' => $bracket['rate']]
            );
        }
    }

    /**
     * BPJS contribution rates and ceilings.
     *
     * JKK varies by industry risk class (0.24%–1.74%); the lowest class is
     * seeded and should be adjusted per company.
     */
    private function seedBpjs(int $year): void
    {
        $rates = [
            [
                'type' => BpjsRate::TYPE_KESEHATAN,
                'employer_rate' => 4.00, 'employee_rate' => 1.00,
                'max_salary' => 12_000_000,
            ],
            [
                'type' => BpjsRate::TYPE_JHT,
                'employer_rate' => 3.70, 'employee_rate' => 2.00,
                'max_salary' => null,
            ],
            [
                'type' => BpjsRate::TYPE_JP,
                'employer_rate' => 2.00, 'employee_rate' => 1.00,
                'max_salary' => 10_042_300,
            ],
            [
                'type' => BpjsRate::TYPE_JKK,
                'employer_rate' => 0.24, 'employee_rate' => 0.00,
                'max_salary' => null,
            ],
            [
                'type' => BpjsRate::TYPE_JKM,
                'employer_rate' => 0.30, 'employee_rate' => 0.00,
                'max_salary' => null,
            ],
        ];

        foreach ($rates as $rate) {
            BpjsRate::updateOrCreate(
                ['year' => $year, 'type' => $rate['type']],
                [
                    'employer_rate' => $rate['employer_rate'],
                    'employee_rate' => $rate['employee_rate'],
                    'max_salary'    => $rate['max_salary'],
                ]
            );
        }
    }
}
