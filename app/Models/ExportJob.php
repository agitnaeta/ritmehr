<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * UM-10 — Status export karyawan batch besar (background).
 *
 * @property int         $id
 * @property int|null    $user_id
 * @property string      $status  queued|processing|done|failed
 * @property int         $total
 * @property string|null $file_path
 * @property string|null $message
 * @property \Carbon\Carbon|null $expires_at
 */
class ExportJob extends Model
{
    public const STATUS_QUEUED = 'queued';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_DONE = 'done';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'user_id', 'type', 'status', 'total', 'file_path', 'message',
        'expires_at', 'started_at', 'finished_at',
    ];

    protected $casts = [
        'total'       => 'integer',
        'expires_at'  => 'datetime',
        'started_at'  => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isFinished(): bool
    {
        return in_array($this->status, [self::STATUS_DONE, self::STATUS_FAILED], true);
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }
}
