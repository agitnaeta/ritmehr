<?php

namespace App\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Pph21Bracket extends Model
{
    use CrudTrait, HasFactory;

    protected $table = 'pph21_brackets';

    protected $fillable = ['year', 'lower_bound', 'upper_bound', 'rate'];

    protected $casts = [
        'year'        => 'integer',
        'lower_bound' => 'integer',
        'upper_bound' => 'integer',
        'rate'        => 'float',
    ];

    public function scopeForYear($query, int $year)
    {
        return $query->where('year', $year)->orderBy('lower_bound');
    }
}
