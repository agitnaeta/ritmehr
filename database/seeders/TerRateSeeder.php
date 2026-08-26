<?php

namespace Database\Seeders;

use App\Models\TerRate;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * M19 — Seed PPh 21 TER rates from a CSV of the official PMK 168/2023 table.
 *
 * Rates are loaded from database/data/ter_rates_<year>.csv rather than typed
 * inline so a compliance reviewer edits one auditable file. Idempotent: an
 * upsert keyed by (year, category, lower_bound). Validates bracket continuity
 * before writing so a gap/overlap fails loudly instead of silently mis-taxing.
 */
class TerRateSeeder extends Seeder
{
    /** Default tax year seeded. Override with TER_YEAR env when needed. */
    public function run(): void
    {
        $year = (int) (env('TER_YEAR') ?: 2026);
        $csv = database_path("data/ter_rates_{$year}.csv");

        if (! is_file($csv)) {
            $this->command?->warn("[TER] CSV not found for year {$year}: {$csv} — skipped.");
            return;
        }

        $rows = $this->parseCsv($csv);
        $this->validate($rows, $year);

        DB::transaction(function () use ($rows, $year) {
            foreach ($rows as $r) {
                TerRate::updateOrCreate(
                    ['year' => $year, 'category' => $r['category'], 'lower_bound' => $r['lower_bound']],
                    ['upper_bound' => $r['upper_bound'], 'rate' => $r['rate']],
                );
            }
        });

        $this->command?->info("[TER] Seeded " . count($rows) . " TER brackets for {$year}.");
    }

    /** @return array<int, array{category:string, lower_bound:int, upper_bound:?int, rate:float}> */
    private function parseCsv(string $path): array
    {
        $rows = [];
        $handle = fopen($path, 'r');
        while (($line = fgets($handle)) !== false) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || str_starts_with($line, 'category,')) {
                continue;
            }
            $cols = str_getcsv($line);
            if (count($cols) < 4) {
                continue;
            }
            $rows[] = [
                'category'    => trim($cols[0]),
                'lower_bound' => (int) $cols[1],
                'upper_bound' => trim((string) $cols[2]) === '' ? null : (int) $cols[2],
                'rate'        => (float) $cols[3],
            ];
        }
        fclose($handle);

        return $rows;
    }

    /**
     * Fail loudly on structural errors: each category must start at 0, be
     * contiguous (no gaps/overlaps), and end with an open-ended top bracket.
     *
     * @param array<int, array{category:string, lower_bound:int, upper_bound:?int, rate:float}> $rows
     */
    private function validate(array $rows, int $year): void
    {
        foreach (['A', 'B', 'C'] as $cat) {
            $bracket = array_values(array_filter($rows, fn ($r) => $r['category'] === $cat));
            if (empty($bracket)) {
                throw new \RuntimeException("[TER {$year}] category {$cat} has no rows.");
            }

            usort($bracket, fn ($a, $b) => $a['lower_bound'] <=> $b['lower_bound']);

            if ($bracket[0]['lower_bound'] !== 0) {
                throw new \RuntimeException("[TER {$year}] category {$cat} must start at 0.");
            }

            $count = count($bracket);
            for ($i = 0; $i < $count - 1; $i++) {
                $upper = $bracket[$i]['upper_bound'];
                $nextLower = $bracket[$i + 1]['lower_bound'];
                if ($upper === null) {
                    throw new \RuntimeException("[TER {$year}] category {$cat} has an open bracket before the end.");
                }
                if ($nextLower !== $upper + 1) {
                    throw new \RuntimeException(
                        "[TER {$year}] category {$cat} gap/overlap: {$upper} → {$nextLower} (expected " . ($upper + 1) . ")."
                    );
                }
            }

            if ($bracket[$count - 1]['upper_bound'] !== null) {
                throw new \RuntimeException("[TER {$year}] category {$cat} last bracket must be open-ended (empty upper_bound).");
            }
        }
    }
}
