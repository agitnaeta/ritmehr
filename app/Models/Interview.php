<?php

namespace App\Models;

use App\Traits\Auditable;
use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Interview extends Model
{
    use CrudTrait, HasFactory, Auditable;

    protected $table = 'interviews';

    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_DONE = 'done';
    public const STATUS_CANCELLED = 'cancelled';

    public const MODE_ONSITE = 'onsite';
    public const MODE_ONLINE = 'online';
    public const MODE_PHONE = 'phone';

    protected $fillable = [
        'applicant_id', 'interviewer_id', 'scheduled_at', 'location',
        'mode', 'status', 'feedback', 'score',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'score'        => 'integer',
    ];

    // ── Relationships ──────────────────────────────────────

    public function applicant()
    {
        return $this->belongsTo(Applicant::class);
    }

    public function interviewer()
    {
        return $this->belongsTo(User::class, 'interviewer_id');
    }

    // ── Scopes ─────────────────────────────────────────────

    public function scopeUpcoming($query)
    {
        return $query->where('status', self::STATUS_SCHEDULED)
                     ->where('scheduled_at', '>=', now());
    }
}
