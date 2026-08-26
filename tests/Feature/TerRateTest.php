<?php

namespace Tests\Feature;

use App\Models\TerRate;
use Database\Seeders\TerRateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * M19-1/M19-2 — TER rate table, model lookup, and CSV seeder integrity.
 */
class TerRateTest extends TestCase
{
    use RefreshDatabase;

    private function seedTer(): void
    {
        $this->seed(TerRateSeeder::class);
    }

    public function test_seeder_loads_all_three_categories(): void
    {
        $this->seedTer();

        $this->assertGreaterThanOrEqual(40, TerRate::where('category', 'A')->count());
        $this->assertGreaterThanOrEqual(35, TerRate::where('category', 'B')->count());
        $this->assertGreaterThanOrEqual(35, TerRate::where('category', 'C')->count());
    }

    public function test_each_category_starts_at_zero_with_zero_rate(): void
    {
        $this->seedTer();

        foreach (['A', 'B', 'C'] as $cat) {
            $first = TerRate::forYearCategory(2026, $cat)->first();
            $this->assertSame(0, $first->lower_bound, "category {$cat} must start at 0");
            $this->assertSame(0.0, $first->rate, "category {$cat} floor must be 0%");
        }
    }

    public function test_each_category_ends_open_ended(): void
    {
        $this->seedTer();

        foreach (['A', 'B', 'C'] as $cat) {
            $last = TerRate::forYearCategory(2026, $cat)->get()->last();
            $this->assertNull($last->upper_bound, "category {$cat} top bracket must be open-ended");
        }
    }

    public function test_brackets_are_contiguous_no_gap_or_overlap(): void
    {
        $this->seedTer();

        foreach (['A', 'B', 'C'] as $cat) {
            $rows = TerRate::forYearCategory(2026, $cat)->get();
            for ($i = 0; $i < $rows->count() - 1; $i++) {
                $this->assertSame(
                    $rows[$i]->upper_bound + 1,
                    $rows[$i + 1]->lower_bound,
                    "category {$cat} must be contiguous at index {$i}"
                );
            }
        }
    }

    public function test_rate_for_resolves_correct_bracket(): void
    {
        $this->seedTer();

        // Category A: 10,000,000 falls in 9,650,001–10,050,000 = 2.00%
        $this->assertSame(2.00, TerRate::rateFor(2026, 'A', 10_000_000));
        // Below the floor → 0%
        $this->assertSame(0.0, TerRate::rateFor(2026, 'A', 5_000_000));
        // Exactly on a lower bound
        $this->assertSame(2.00, TerRate::rateFor(2026, 'A', 9_650_001));
    }

    public function test_rate_for_returns_null_when_year_absent(): void
    {
        $this->seedTer();
        $this->assertNull(TerRate::rateFor(1999, 'A', 10_000_000));
    }

    public function test_seeder_is_idempotent(): void
    {
        $this->seedTer();
        $count = TerRate::count();
        $this->seedTer(); // run again
        $this->assertSame($count, TerRate::count(), 'reseeding must not duplicate rows');
    }
}
