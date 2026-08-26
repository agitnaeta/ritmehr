<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * M20 — Frozen snapshot of one allowance line on a payslip (recap). Label is
 * copied so editing the master type later never alters historical slips.
 */
class SalaryRecapAllowance extends Model
{
    protected $table = 'salary_recap_allowances';

    protected $fillable = ['salary_recap_id', 'label', 'amount'];

    protected $casts = ['amount' => 'integer'];

    public function recap()
    {
        return $this->belongsTo(SalaryRecap::class, 'salary_recap_id');
    }
}
