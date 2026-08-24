<?php

namespace Tests\Feature;

use App\Models\Approval;
use App\Models\ApprovalFlow;
use App\Models\ApprovalFlowStep;
use App\Models\Loan;
use App\Models\User;
use App\Services\ApprovalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ApprovalEngineTest extends TestCase
{
    use RefreshDatabase;

    private ApprovalService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ApprovalService::class);
    }

    // ── Helpers ────────────────────────────────────────────

    private function makeUser(string $name, ?int $managerId = null): User
    {
        return User::create([
            'name'       => $name,
            'email'      => str($name)->slug() . '@example.test',
            'password'   => bcrypt('secret'),
            'manager_id' => $managerId,
        ]);
    }

    private function makeLoan(User $user): Loan
    {
        return Loan::create([
            'user_id' => $user->id,
            'amount'  => 1_000_000,
            'date'    => now()->toDateString(),
        ]);
    }

    /**
     * Build a flow whose steps are described as
     * [['type' => ..., 'role' => Role|null, 'user' => User|null], ...]
     */
    private function makeFlow(string $module, array $steps): ApprovalFlow
    {
        $flow = ApprovalFlow::create([
            'name'      => ucfirst($module) . ' Flow',
            'module'    => $module,
            'steps'     => count($steps),
            'is_active' => true,
        ]);

        foreach ($steps as $i => $step) {
            ApprovalFlowStep::create([
                'approval_flow_id' => $flow->id,
                'step_order'       => $i + 1,
                'approver_type'    => $step['type'],
                'approver_role_id' => $step['role'] ?? null,
                'approver_user_id' => $step['user'] ?? null,
            ]);
        }

        return $flow->fresh('flowSteps');
    }

    // ── Tests ──────────────────────────────────────────────

    public function test_single_step_manager_approval_completes_the_chain(): void
    {
        $manager = $this->makeUser('Manager');
        $staff = $this->makeUser('Staff', $manager->id);

        $this->makeFlow('loan', [['type' => ApprovalFlowStep::TYPE_MANAGER]]);
        $loan = $this->makeLoan($staff);

        $approval = $this->service->submitForApproval($loan, $staff, 'loan');

        $this->assertTrue($approval->isPending());
        $this->assertSame(1, $approval->current_step);

        $this->service->approve($approval, $manager, 'ok');

        $this->assertTrue($approval->fresh()->isApproved());
        $this->assertDatabaseHas('approval_actions', [
            'approval_id' => $approval->id,
            'action'      => 'approve',
            'acted_by'    => $manager->id,
        ]);
    }

    public function test_two_step_chain_advances_before_it_resolves(): void
    {
        $hrRole = Role::create(['name' => 'hr_admin', 'guard_name' => 'web']);
        $manager = $this->makeUser('Manager');
        $staff = $this->makeUser('Staff', $manager->id);
        $hr = $this->makeUser('HR');
        $hr->assignRole($hrRole);

        $this->makeFlow('loan', [
            ['type' => ApprovalFlowStep::TYPE_MANAGER],
            ['type' => ApprovalFlowStep::TYPE_ROLE, 'role' => $hrRole->id],
        ]);

        $approval = $this->service->submitForApproval($this->makeLoan($staff), $staff, 'loan');

        // Step 1 — manager approves, chain advances but is not yet approved.
        $this->service->approve($approval, $manager);
        $approval->refresh();
        $this->assertTrue($approval->isPending());
        $this->assertSame(2, $approval->current_step);

        // Step 2 — HR closes it out.
        $this->service->approve($approval, $hr);
        $this->assertTrue($approval->fresh()->isApproved());
    }

    public function test_wrong_approver_is_rejected_at_each_step(): void
    {
        $hrRole = Role::create(['name' => 'hr_admin', 'guard_name' => 'web']);
        $manager = $this->makeUser('Manager');
        $staff = $this->makeUser('Staff', $manager->id);
        $hr = $this->makeUser('HR');
        $hr->assignRole($hrRole);
        $outsider = $this->makeUser('Outsider');

        $this->makeFlow('loan', [
            ['type' => ApprovalFlowStep::TYPE_MANAGER],
            ['type' => ApprovalFlowStep::TYPE_ROLE, 'role' => $hrRole->id],
        ]);

        $approval = $this->service->submitForApproval($this->makeLoan($staff), $staff, 'loan');

        // HR cannot jump ahead to step 1, which belongs to the manager.
        $this->expectException(\DomainException::class);
        $this->service->approve($approval, $hr);
    }

    public function test_unrelated_user_cannot_approve(): void
    {
        $manager = $this->makeUser('Manager');
        $staff = $this->makeUser('Staff', $manager->id);
        $outsider = $this->makeUser('Outsider');

        $this->makeFlow('loan', [['type' => ApprovalFlowStep::TYPE_MANAGER]]);
        $approval = $this->service->submitForApproval($this->makeLoan($staff), $staff, 'loan');

        $this->expectException(\DomainException::class);
        $this->service->approve($approval, $outsider);
    }

    public function test_rejection_ends_the_chain_immediately(): void
    {
        $hrRole = Role::create(['name' => 'hr_admin', 'guard_name' => 'web']);
        $manager = $this->makeUser('Manager');
        $staff = $this->makeUser('Staff', $manager->id);

        $this->makeFlow('loan', [
            ['type' => ApprovalFlowStep::TYPE_MANAGER],
            ['type' => ApprovalFlowStep::TYPE_ROLE, 'role' => $hrRole->id],
        ]);

        $approval = $this->service->submitForApproval($this->makeLoan($staff), $staff, 'loan');
        $this->service->reject($approval, $manager, 'Dana tidak tersedia');

        $approval->refresh();
        $this->assertTrue($approval->isRejected());
        // Still on step 1 — a rejection does not advance the chain.
        $this->assertSame(1, $approval->current_step);
    }

    public function test_rejection_requires_a_reason(): void
    {
        $manager = $this->makeUser('Manager');
        $staff = $this->makeUser('Staff', $manager->id);
        $this->makeFlow('loan', [['type' => ApprovalFlowStep::TYPE_MANAGER]]);
        $approval = $this->service->submitForApproval($this->makeLoan($staff), $staff, 'loan');

        $this->expectException(\DomainException::class);
        $this->service->reject($approval, $manager, '   ');
    }

    public function test_a_resolved_approval_cannot_be_acted_on_again(): void
    {
        $manager = $this->makeUser('Manager');
        $staff = $this->makeUser('Staff', $manager->id);
        $this->makeFlow('loan', [['type' => ApprovalFlowStep::TYPE_MANAGER]]);
        $approval = $this->service->submitForApproval($this->makeLoan($staff), $staff, 'loan');

        $this->service->approve($approval, $manager);

        $this->expectException(\DomainException::class);
        $this->service->approve($approval->fresh(), $manager);
    }

    public function test_only_the_requester_can_cancel(): void
    {
        $manager = $this->makeUser('Manager');
        $staff = $this->makeUser('Staff', $manager->id);
        $this->makeFlow('loan', [['type' => ApprovalFlowStep::TYPE_MANAGER]]);
        $approval = $this->service->submitForApproval($this->makeLoan($staff), $staff, 'loan');

        try {
            $this->service->cancel($approval, $manager);
            $this->fail('Manager should not be able to cancel someone else\'s request.');
        } catch (\DomainException $e) {
            // expected
        }

        $this->service->cancel($approval, $staff);
        $this->assertTrue($approval->fresh()->isCancelled());
    }

    public function test_duplicate_submission_is_refused(): void
    {
        $manager = $this->makeUser('Manager');
        $staff = $this->makeUser('Staff', $manager->id);
        $this->makeFlow('loan', [['type' => ApprovalFlowStep::TYPE_MANAGER]]);
        $loan = $this->makeLoan($staff);

        $this->service->submitForApproval($loan, $staff, 'loan');

        $this->expectException(\DomainException::class);
        $this->service->submitForApproval($loan, $staff, 'loan');
    }

    public function test_submitting_without_a_configured_flow_fails_loudly(): void
    {
        $staff = $this->makeUser('Staff');

        $this->expectException(\RuntimeException::class);
        $this->service->submitForApproval($this->makeLoan($staff), $staff, 'loan');
    }

    public function test_manager_step_is_unsatisfiable_when_requester_has_no_manager(): void
    {
        $staff = $this->makeUser('Orphan');  // no manager_id
        $someone = $this->makeUser('Someone');

        $this->makeFlow('loan', [['type' => ApprovalFlowStep::TYPE_MANAGER]]);
        $approval = $this->service->submitForApproval($this->makeLoan($staff), $staff, 'loan');

        $this->assertFalse($approval->canBeActedOnBy($someone));
        $this->assertFalse($approval->canBeActedOnBy($staff));
    }

    public function test_pending_inbox_lists_only_actionable_requests(): void
    {
        $hrRole = Role::create(['name' => 'hr_admin', 'guard_name' => 'web']);
        $manager = $this->makeUser('Manager');
        $otherManager = $this->makeUser('Other Manager');
        $staff = $this->makeUser('Staff', $manager->id);
        $otherStaff = $this->makeUser('Other Staff', $otherManager->id);
        $hr = $this->makeUser('HR');
        $hr->assignRole($hrRole);

        $this->makeFlow('loan', [
            ['type' => ApprovalFlowStep::TYPE_MANAGER],
            ['type' => ApprovalFlowStep::TYPE_ROLE, 'role' => $hrRole->id],
        ]);

        $a = $this->service->submitForApproval($this->makeLoan($staff), $staff, 'loan');
        $b = $this->service->submitForApproval($this->makeLoan($otherStaff), $otherStaff, 'loan');

        // Each manager sees only their own subordinate's request.
        $this->assertEquals([$a->id], $this->service->getPendingForUser($manager)->pluck('id')->all());
        $this->assertEquals([$b->id], $this->service->getPendingForUser($otherManager)->pluck('id')->all());

        // HR sees nothing yet — both are still on step 1.
        $this->assertCount(0, $this->service->getPendingForUser($hr));

        // Once A clears step 1, it lands in HR's inbox.
        $this->service->approve($a, $manager);
        $this->assertEquals([$a->id], $this->service->getPendingForUser($hr)->pluck('id')->all());
        $this->assertCount(0, $this->service->getPendingForUser($manager));
    }

    public function test_next_approvers_resolves_role_steps_to_every_holder(): void
    {
        $hrRole = Role::create(['name' => 'hr_admin', 'guard_name' => 'web']);
        $staff = $this->makeUser('Staff');
        $hr1 = $this->makeUser('HR One');
        $hr2 = $this->makeUser('HR Two');
        $hr1->assignRole($hrRole);
        $hr2->assignRole($hrRole);

        $this->makeFlow('loan', [['type' => ApprovalFlowStep::TYPE_ROLE, 'role' => $hrRole->id]]);
        $approval = $this->service->submitForApproval($this->makeLoan($staff), $staff, 'loan');

        $this->assertEqualsCanonicalizing(
            [$hr1->id, $hr2->id],
            $this->service->getNextApprovers($approval)->pluck('id')->all()
        );
    }

    public function test_specific_user_step_only_admits_that_user(): void
    {
        $staff = $this->makeUser('Staff');
        $director = $this->makeUser('Director');
        $imposter = $this->makeUser('Imposter');

        $this->makeFlow('loan', [
            ['type' => ApprovalFlowStep::TYPE_SPECIFIC_USER, 'user' => $director->id],
        ]);
        $approval = $this->service->submitForApproval($this->makeLoan($staff), $staff, 'loan');

        $this->assertFalse($approval->canBeActedOnBy($imposter));
        $this->assertTrue($approval->canBeActedOnBy($director));

        $this->service->approve($approval, $director);
        $this->assertTrue($approval->fresh()->isApproved());
    }

    public function test_inactive_flow_is_not_used(): void
    {
        $staff = $this->makeUser('Staff');
        $flow = $this->makeFlow('loan', [['type' => ApprovalFlowStep::TYPE_MANAGER]]);
        $flow->update(['is_active' => false]);

        $this->expectException(\RuntimeException::class);
        $this->service->submitForApproval($this->makeLoan($staff), $staff, 'loan');
    }
}
