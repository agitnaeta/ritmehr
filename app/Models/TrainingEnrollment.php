<?php

namespace App\Models;

use App\Traits\Auditable;
use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * M11 — a participant's enrollment in a training + their auto-graded result.
 * status: enrolled → passed | failed | locked (after max_attempts fails).
 */
class TrainingEnrollment extends Model
{
    use CrudTrait, HasFactory, Auditable;

    protected $table = 'training_enrollments';

    public const STATUS_ENROLLED = 'enrolled';
    public const STATUS_PASSED = 'passed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_LOCKED = 'locked';

    public const STATUS_LABELS = [
        self::STATUS_ENROLLED => 'Belum Selesai',
        self::STATUS_PASSED   => 'Lulus',
        self::STATUS_FAILED   => 'Tidak Lulus',
        self::STATUS_LOCKED   => 'Terkunci',
    ];

    protected $fillable = [
        'training_id', 'user_id', 'status', 'score', 'attempts',
        'passed_at', 'certificate_no', 'certificate_issued_at',
    ];

    protected $casts = [
        'score'                 => 'integer',
        'attempts'              => 'integer',
        'passed_at'             => 'datetime',
        'certificate_issued_at' => 'datetime',
    ];

    public function training()
    {
        return $this->belongsTo(Training::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    public function isPassed(): bool
    {
        return $this->status === self::STATUS_PASSED;
    }

    public function isLocked(): bool
    {
        return $this->status === self::STATUS_LOCKED;
    }
}
