<?php

namespace App\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Salary extends Model
{
    use CrudTrait;
    use HasFactory;
    use \App\Traits\Auditable;
    protected $fillable = [
        'user_id', 'basic_salary', 'amount', 'overtime_amount', 'overtime_type','unpaid_leave_deduction',
        'fine_per_minute','fine_type','fine','extra_time','extra_time_rule'
    ];

    protected $casts = ['amount' => 'integer', 'basic_salary' => 'integer'];

    /**
     * M20 — Keep amount = basic_salary + Σ active allowances on every save, so
     * downstream tax/BPJS (which read `amount`) never need to change.
     *
     * Backward-compat: legacy callers that set `amount` directly without a
     * `basic_salary` still work — we treat that amount as the basic salary.
     */
    protected static function booted(): void
    {
        static::saving(function (Salary $salary) {
            // Legacy path: amount set, basic_salary not → seed basic from amount.
            if ((int) $salary->basic_salary === 0 && (int) $salary->amount > 0
                && ! EmployeeSalaryAllowance::where('user_id', $salary->user_id)->exists()) {
                $salary->basic_salary = (int) $salary->amount;
            }

            $allowances = EmployeeSalaryAllowance::where('user_id', $salary->user_id)
                ->whereHas('type', fn ($q) => $q->where('is_active', true))
                ->sum('amount');
            $salary->amount = (int) $salary->basic_salary + (int) $allowances;
        });
    }

    public function user(){
        return $this->hasOne(User::class,'id','user_id');
    }

    /** M20 — this employee's allowance values (per global type). */
    public function allowances()
    {
        return $this->hasMany(EmployeeSalaryAllowance::class, 'user_id', 'user_id');
    }

    /**
     * M20 — Recompute amount = basic_salary + Σ active allowances, so tax/BPJS
     * (which read `amount`) stay unchanged while the breakdown is visible.
     */
    public function recalcTotal(): void
    {
        $allowances = EmployeeSalaryAllowance::where('user_id', $this->user_id)
            ->whereHas('type', fn ($q) => $q->where('is_active', true))
            ->sum('amount');

        $this->amount = (int) $this->basic_salary + (int) $allowances;
        $this->saveQuietly();
    }
}
