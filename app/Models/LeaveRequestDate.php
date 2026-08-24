<?php

namespace App\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeaveRequestDate extends Model
{
    use CrudTrait, HasFactory;

    protected $table = 'leave_request_dates';

    protected $fillable = [
        'leave_request_id',
        'date',
        'day_value',
    ];

    protected $casts = [
        'date'      => 'date',
        'day_value' => 'float',
    ];

    public function leaveRequest()
    {
        return $this->belongsTo(LeaveRequest::class);
    }
}
