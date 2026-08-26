<?php

namespace App\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * M19 — One effective-rate bracket of the PPh 21 TER table for a given year and
 * category (A/B/C). See PMK 168/2023.
 */
class TerRate extends Model
{
    use CrudTrait, HasFactory;

    protected $table = 'ter_rates';

    protected $fillable = ['year', 'category', 'lower_bound', 'upper_bound', 'rate'];

    protected $casts = [
        'year'        => 'integer',
        'lower_bound' => 'integer',
        'upper_bound' => 'integer',
        'rate'        => 'float',
    ];

    public const CATEGORIES = ['A', 'B', 'C'];

    /** Brackets for a year + category, ordered low→high. */
    public function scopeForYearCategory($query, int $year, string $category)
    {
        return $query->where('year', $year)
            ->where('category', $category)
            ->orderBy('lower_bound');
    }

    /**
     * Resolve the effective rate (percent) for a gross monthly amount in a
     * given year + category. Returns null when no table is configured so the
     * caller can decide (we never invent a rate).
     */
    public static function rateFor(int $year, string $category, int $gross): ?float
    {
        $row = static::where('year', $year)
            ->where('category', $category)
            ->where('lower_bound', '<=', $gross)
            ->where(function ($q) use ($gross) {
                $q->whereNull('upper_bound')->orWhere('upper_bound', '>=', $gross);
            })
            ->orderByDesc('lower_bound')
            ->first();

        return $row?->rate;
    }
}
