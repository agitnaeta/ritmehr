<?php

namespace App\Models;

use App\Traits\Auditable;
use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Review extends Model
{
    use CrudTrait, HasFactory, Auditable;

    protected $table = 'reviews';

    public const STATUS_PENDING = 'pending';
    public const STATUS_SELF_SUBMITTED = 'self_submitted';
    public const STATUS_MANAGER_SUBMITTED = 'manager_submitted';
    public const STATUS_FINALIZED = 'finalized';

    public const STATUS_LABELS = [
        self::STATUS_PENDING           => 'Menunggu',
        self::STATUS_SELF_SUBMITTED    => 'Self-review Terkirim',
        self::STATUS_MANAGER_SUBMITTED => 'Dinilai Manajer',
        self::STATUS_FINALIZED         => 'Selesai',
    ];

    protected $fillable = [
        'review_cycle_id', 'user_id', 'reviewer_id', 'status',
        'self_comment', 'manager_comment', 'final_score',
        'self_submitted_at', 'finalized_at',
    ];

    protected $casts = [
        'final_score'       => 'float',
        'self_submitted_at' => 'datetime',
        'finalized_at'      => 'datetime',
    ];

    // ── Relationships ──────────────────────────────────────

    public function cycle()
    {
        return $this->belongsTo(ReviewCycle::class, 'review_cycle_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    public function items()
    {
        return $this->hasMany(ReviewItem::class);
    }

    // ── Helpers ────────────────────────────────────────────

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    public function isFinalized(): bool
    {
        return $this->status === self::STATUS_FINALIZED;
    }
}
