<?php

namespace App\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    use CrudTrait, HasFactory;

    // Event types. Keep in sync with NotificationType::labels().
    public const LATE_ALERT = 'late_alert';
    public const MISSING_CHECKIN = 'missing_checkin';
    public const MISSING_CHECKOUT = 'missing_checkout';
    public const OUT_OF_RADIUS = 'out_of_radius';
    public const LEAVE_SUBMITTED = 'leave_submitted';
    public const LEAVE_APPROVED = 'leave_approved';
    public const LEAVE_REJECTED = 'leave_rejected';
    public const LEAVE_BALANCE_LOW = 'leave_balance_low';
    public const SALARY_PAID = 'salary_paid';
    public const LOAN_CREATED = 'loan_created';
    public const APPROVAL_PENDING = 'approval_pending';
    public const APPROVAL_DIGEST = 'approval_digest';
    public const DOCUMENT_EXPIRING = 'document_expiring';

    public const CHANNEL_DATABASE = 'database';
    public const CHANNEL_EMAIL = 'email';
    public const CHANNEL_WHATSAPP = 'whatsapp';
    public const CHANNEL_PUSH = 'push';

    protected $table = 'notifications';

    protected $fillable = [
        'user_id',
        'type',
        'title',
        'body',
        'data',
        'channel',
        'read_at',
        'sent_at',
    ];

    protected $casts = [
        'data'    => 'array',
        'read_at' => 'datetime',
        'sent_at' => 'datetime',
    ];

    // ── Relationships ──────────────────────────────────────

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // ── Scopes ─────────────────────────────────────────────

    public function scopeUnread($query)
    {
        return $query->whereNull('read_at');
    }

    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    // ── Helpers ────────────────────────────────────────────

    public function isRead(): bool
    {
        return $this->read_at !== null;
    }

    /**
     * Where clicking the notification should take the recipient.
     */
    public function url(): ?string
    {
        $prefix = config('backpack.base.route_prefix', 'admin');
        $data = $this->data ?? [];

        return match ($this->type) {
            self::LEAVE_SUBMITTED, self::LEAVE_APPROVED, self::LEAVE_REJECTED =>
                isset($data['leave_request_id'])
                    ? url("{$prefix}/leave-request/{$data['leave_request_id']}/show")
                    : url("{$prefix}/leave-request"),

            self::APPROVAL_PENDING =>
                isset($data['approval_id'])
                    ? url("{$prefix}/approval/{$data['approval_id']}/detail")
                    : url("{$prefix}/approval"),

            self::APPROVAL_DIGEST => url("{$prefix}/approval"),

            self::SALARY_PAID =>
                isset($data['salary_recap_id'])
                    ? url("{$prefix}/salary-recap/{$data['salary_recap_id']}/show")
                    : url("{$prefix}/salary-recap"),

            self::LOAN_CREATED =>
                isset($data['loan_id'])
                    ? url("{$prefix}/loan/{$data['loan_id']}/detail")
                    : url("{$prefix}/loan"),

            self::LATE_ALERT, self::MISSING_CHECKIN,
            self::MISSING_CHECKOUT, self::OUT_OF_RADIUS => url("{$prefix}/presence"),

            self::DOCUMENT_EXPIRING => url("{$prefix}/employee-document"),

            default => null,
        };
    }

    public function icon(): string
    {
        return match ($this->type) {
            self::LATE_ALERT, self::MISSING_CHECKIN, self::MISSING_CHECKOUT => 'la-clock',
            self::OUT_OF_RADIUS => 'la-map-marker',
            self::LEAVE_SUBMITTED, self::LEAVE_APPROVED, self::LEAVE_REJECTED,
            self::LEAVE_BALANCE_LOW => 'la-umbrella-beach',
            self::SALARY_PAID => 'la-money-bill',
            self::LOAN_CREATED => 'la-hand-holding-usd',
            self::APPROVAL_PENDING, self::APPROVAL_DIGEST => 'la-check-double',
            self::DOCUMENT_EXPIRING => 'la-file-alt',
            default => 'la-bell',
        };
    }
}
