<?php

namespace App\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Presence extends Model
{
    use CrudTrait;
    use HasFactory;
    use \App\Traits\Auditable;
    protected $fillable = [
        'in',
        'out',
        'user_id',
        'is_overtime',
        'is_late',
        'late_minute',
        'lat',
        'lng',
        'outside',
        'extra_time',
        'branch_id',
    ];

    public function user(){
        return $this->belongsTo(User::class);
    }

    public function branch(){
        return $this->belongsTo(Branch::class);
    }
}
