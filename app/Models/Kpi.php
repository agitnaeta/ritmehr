<?php

namespace App\Models;

use App\Traits\Auditable;
use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Kpi extends Model
{
    use CrudTrait, HasFactory, Auditable;

    protected $table = 'kpis';

    protected $fillable = [
        'name', 'description', 'weight', 'is_active',
    ];

    protected $casts = [
        'weight'    => 'integer',
        'is_active' => 'boolean',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
