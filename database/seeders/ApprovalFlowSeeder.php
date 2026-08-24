<?php

namespace Database\Seeders;

use App\Models\ApprovalFlow;
use App\Models\ApprovalFlowStep;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

/**
 * Sensible default chains: the requester's manager first, then HR.
 *
 * Safe to re-run — flows are matched by module and their steps are replaced,
 * so this will not stack duplicate steps.
 */
class ApprovalFlowSeeder extends Seeder
{
    public function run(): void
    {
        $hrRole = Role::where('name', 'hr_admin')->where('guard_name', 'web')->first();

        if (! $hrRole) {
            $this->command?->warn('Role hr_admin not found — run RolesAndPermissionsSeeder first.');

            return;
        }

        $defaults = [
            'leave' => 'Pengajuan Cuti / Izin',
            'loan'  => 'Pengajuan Kasbon',
        ];

        foreach ($defaults as $module => $name) {
            $flow = ApprovalFlow::firstOrNew(['module' => $module]);
            $flow->fill(['name' => $name, 'steps' => 2, 'is_active' => true])->save();

            $flow->flowSteps()->delete();

            ApprovalFlowStep::create([
                'approval_flow_id' => $flow->id,
                'step_order'       => 1,
                'approver_type'    => ApprovalFlowStep::TYPE_MANAGER,
            ]);

            ApprovalFlowStep::create([
                'approval_flow_id' => $flow->id,
                'step_order'       => 2,
                'approver_type'    => ApprovalFlowStep::TYPE_ROLE,
                'approver_role_id' => $hrRole->id,
            ]);

            $this->command?->info("Approval flow [{$name}] configured with 2 steps.");
        }
    }
}
