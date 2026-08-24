<?php

namespace App\Models;

use App\Traits\Auditable;
use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Position extends Model
{
    use CrudTrait, HasFactory, Auditable;

    protected $table = 'positions';

    protected $fillable = [
        'name',
        'level',
        'department_id',
    ];

    protected $casts = [
        'level' => 'integer',
    ];

    // ── Relationships ──────────────────────────────────────

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function users()
    {
        return $this->hasMany(User::class, 'position_id');
    }

    // ── Scopes ─────────────────────────────────────────────

    public function scopeOrderedByLevel($query)
    {
        return $query->orderByDesc('level')->orderBy('name');
    }
}
