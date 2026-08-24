<?php

namespace App\Models;

use App\Traits\Auditable;
use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApprovalFlow extends Model
{
    use CrudTrait, HasFactory, Auditable;

    protected $table = 'approval_flows';

    protected $fillable = [
        'name',
        'module',
        'steps',
        'is_active',
    ];

    protected $casts = [
        'steps'     => 'integer',
        'is_active' => 'boolean',
    ];

    // ── Relationships ──────────────────────────────────────

    public function flowSteps()
    {
        return $this->hasMany(ApprovalFlowStep::class)->orderBy('step_order');
    }

    public function approvals()
    {
        return $this->hasMany(Approval::class);
    }

    // ── Scopes ─────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForModule($query, string $module)
    {
        return $query->where('module', $module);
    }

    // ── Helpers ────────────────────────────────────────────

    /**
     * The active flow configured for a module, or null if none is set up.
     */
    public static function forModuleOrFail(string $module): self
    {
        $flow = static::active()->forModule($module)->with('flowSteps')->first();

        if (! $flow) {
            throw new \RuntimeException("No active approval flow configured for module [{$module}].");
        }

        if ($flow->flowSteps->isEmpty()) {
            throw new \RuntimeException("Approval flow [{$flow->name}] has no steps configured.");
        }

        return $flow;
    }

    public function stepAt(int $order): ?ApprovalFlowStep
    {
        return $this->flowSteps->firstWhere('step_order', $order);
    }

    /**
     * Number of steps actually configured, which is the authoritative count —
     * the `steps` column is only a display hint and can drift.
     */
    public function totalSteps(): int
    {
        return $this->flowSteps->count();
    }
}
