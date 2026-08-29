<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * UM-09 — Status & progress satu proses import background.
 *
 * @property int         $id
 * @property int|null    $user_id
 * @property string      $type
 * @property string|null $original_name
 * @property string|null $file_path
 * @property string      $status      queued|processing|done|failed
 * @property int         $total_rows
 * @property int         $processed
 * @property int         $imported
 * @property int         $skipped
 * @property array|null  $errors      [{row,column,value,reason}]
 * @property string|null $message
 */
class ImportJob extends Model
{
    public const STATUS_QUEUED = 'queued';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_DONE = 'done';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'user_id', 'type', 'original_name', 'file_path', 'status',
        'total_rows', 'processed', 'imported', 'skipped',
        'errors', 'message', 'started_at', 'finished_at',
    ];

    protected $casts = [
        'errors'      => 'array',
        'total_rows'  => 'integer',
        'processed'   => 'integer',
        'imported'    => 'integer',
        'skipped'     => 'integer',
        'started_at'  => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Persentase progress 0–100 (aman saat total 0). */
    public function getProgressAttribute(): int
    {
        if ($this->total_rows <= 0) {
            return $this->status === self::STATUS_DONE ? 100 : 0;
        }

        return (int) min(100, round($this->processed / $this->total_rows * 100));
    }

    public function isFinished(): bool
    {
        return in_array($this->status, [self::STATUS_DONE, self::STATUS_FAILED], true);
    }

    /** Scope: hanya import milik viewer (uploader). HR/admin bisa diperluas di controller. */
    public function scopeOwnedBy($query, ?User $user)
    {
        return $query->where('user_id', $user?->id);
    }
}
