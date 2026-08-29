<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * UM-11 — Status generate PDF kartu ID batch besar (background).
 *
 * @property int         $id
 * @property int|null    $user_id
 * @property string      $status  queued|processing|done|failed
 * @property int         $total
 * @property int         $processed
 * @property array|null  $target_ids
 * @property string|null $file_path
 * @property string|null $message
 */
class PrintJob extends Model
{
    public const STATUS_QUEUED = 'queued';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_DONE = 'done';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'user_id', 'type', 'status', 'total', 'processed',
        'target_ids', 'file_path', 'message', 'started_at', 'finished_at',
    ];

    protected $casts = [
        'target_ids'  => 'array',
        'total'       => 'integer',
        'processed'   => 'integer',
        'started_at'  => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getProgressAttribute(): int
    {
        if ($this->total <= 0) {
            return $this->status === self::STATUS_DONE ? 100 : 0;
        }

        return (int) min(100, round($this->processed / $this->total * 100));
    }

    public function isFinished(): bool
    {
        return in_array($this->status, [self::STATUS_DONE, self::STATUS_FAILED], true);
    }
}
