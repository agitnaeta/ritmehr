<?php

namespace App\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BpjsRate extends Model
{
    use CrudTrait, HasFactory;

    public const TYPE_KESEHATAN = 'kesehatan';
    public const TYPE_JHT = 'jht';
    public const TYPE_JP = 'jp';
    public const TYPE_JKK = 'jkk';
    public const TYPE_JKM = 'jkm';

    protected $table = 'bpjs_rates';

    protected $fillable = ['year', 'type', 'employer_rate', 'employee_rate', 'max_salary'];

    protected $casts = [
        'year'          => 'integer',
        'employer_rate' => 'float',
        'employee_rate' => 'float',
        'max_salary'    => 'integer',
    ];

    public function scopeForYear($query, int $year)
    {
        return $query->where('year', $year);
    }

    /**
     * Contributions are calculated on salary capped at max_salary where a
     * ceiling applies (JP and Kesehatan both have one).
     */
    public function cappedBase(int $salary): int
    {
        return $this->max_salary ? min($salary, $this->max_salary) : $salary;
    }
}
