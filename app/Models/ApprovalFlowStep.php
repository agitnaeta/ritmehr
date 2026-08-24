<?php

namespace App\Models;

use App\Traits\Auditable;
use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;


class ApprovalFlowStep extends Model
{
    use CrudTrait, HasFactory, Auditable;

    public const TYPE_ROLE = 'role';
    public const TYPE_MANAGER = 'manager';
    public const TYPE_SPECIFIC_USER = 'specific_user';

    protected $table = 'approval_flow_steps';

    protected $fillable = [
        'approval_flow_id',
        'step_order',
        'approver_type',
        'approver_role_id',
        'approver_user_id',
    ];

    protected $casts = [
        'step_order' => 'integer',
    ];

    // ── Relationships ──────────────────────────────────────

    public function approvalFlow()
    {
        return $this->belongsTo(ApprovalFlow::class);
    }

    public function approverRole()
    {
        return $this->belongsTo(Role::class, 'approver_role_id');
    }

    public function approverUser()
    {
        return $this->belongsTo(User::class, 'approver_user_id');
    }

    // ── Helpers ────────────────────────────────────────────

    /**
     * Can $user act on this step for a request raised by $requester?
     */
    public function isSatisfiedBy(User $user, User $requester): bool
    {
        return match ($this->approver_type) {
            self::TYPE_ROLE => $this->approver_role_id
                && $user->roles->contains('id', $this->approver_role_id),

            self::TYPE_MANAGER => $requester->manager_id !== null
                && (int) $requester->manager_id === (int) $user->id,

            self::TYPE_SPECIFIC_USER => $this->approver_user_id !== null
                && (int) $this->approver_user_id === (int) $user->id,

            default => false,
        };
    }

    public function describe(): string
    {
        return match ($this->approver_type) {
            self::TYPE_ROLE => 'Role: ' . ($this->approverRole?->name ?? '—'),
            self::TYPE_MANAGER => 'Atasan langsung pemohon',
            self::TYPE_SPECIFIC_USER => 'User: ' . ($this->approverUser?->name ?? '—'),
            default => '—',
        };
    }
}
