<?php

namespace App\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * M20 — An allowance value for one employee (links a user to a global allowance
 * type with a monthly amount). Not filled = no row = 0.
 */
class EmployeeSalaryAllowance extends Model
{
    use CrudTrait, HasFactory;
    use \App\Traits\Auditable;

    protected $table = 'employee_salary_allowances';

    protected $fillable = ['user_id', 'salary_allowance_type_id', 'amount'];

    protected $casts = ['amount' => 'integer'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function type()
    {
        return $this->belongsTo(SalaryAllowanceType::class, 'salary_allowance_type_id');
    }
}
