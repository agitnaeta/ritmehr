<?php

namespace App\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReviewItem extends Model
{
    use CrudTrait, HasFactory;

    protected $table = 'review_items';

    protected $fillable = [
        'review_id', 'kpi_id', 'self_score', 'manager_score', 'weight',
    ];

    protected $casts = [
        'self_score'    => 'integer',
        'manager_score' => 'integer',
        'weight'        => 'integer',
    ];

    public function review()
    {
        return $this->belongsTo(Review::class);
    }

    public function kpi()
    {
        return $this->belongsTo(Kpi::class);
    }
}
