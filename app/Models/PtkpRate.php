<?php

namespace App\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PtkpRate extends Model
{
    use CrudTrait, HasFactory;

    protected $table = 'ptkp_rates';

    protected $fillable = ['year', 'status', 'amount'];

    protected $casts = ['year' => 'integer', 'amount' => 'integer'];
}
