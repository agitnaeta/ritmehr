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
        'user_id', 'amount', 'overtime_amount', 'overtime_type','unpaid_leave_deduction',
        'fine_per_minute','fine_type','fine','extra_time','extra_time_rule'
    ];

    public function user(){
        return $this->hasOne(User::class,'id','user_id');
    }
}
