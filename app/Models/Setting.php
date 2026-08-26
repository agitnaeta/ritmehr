<?php

namespace App\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    use CrudTrait;
    use HasFactory;

    protected $fillable = [
        'key', 'value', 'group', 'type', 'is_encrypted',
    ];

    protected $casts = [
        'is_encrypted' => 'boolean',
    ];
}
