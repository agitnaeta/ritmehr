<?php

namespace App\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApprovalAction extends Model
{
    use CrudTrait, HasFactory;

    public const ACTION_APPROVE = 'approve';
    public const ACTION_REJECT = 'reject';

    protected $table = 'approval_actions';

    public const UPDATED_AT = null;

    protected $fillable = [
        'approval_id',
        'step_order',
        'action',
        'acted_by',
        'notes',
        'acted_at',
    ];

    protected $casts = [
        'step_order' => 'integer',
        'acted_at'   => 'datetime',
    ];

    // ── Relationships ──────────────────────────────────────

    public function approval()
    {
        return $this->belongsTo(Approval::class);
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'acted_by');
    }
}
