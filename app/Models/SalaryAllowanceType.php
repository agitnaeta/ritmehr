<?php

namespace App\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * M20 — Global master list of salary allowance types (free label, reusable
 * across employees). E.g. "Tunjangan Jabatan", "Transport", "Uang Makan".
 */
class SalaryAllowanceType extends Model
{
    use CrudTrait, HasFactory;
    use \App\Traits\Auditable;

    protected $fillable = ['label', 'sort_order', 'is_active'];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active'  => 'boolean',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
