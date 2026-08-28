<?php

namespace Tests\Feature;

use App\Models\Presence;
use App\Models\User;
use App\Services\PresenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * M22-5 — Approval for out-of-radius camera check-ins.
 *
 * A pending record transitions to approved/rejected, records who decided,
 * notifies the employee, and stays idempotent. The manager screen is guarded
 * by presence.edit and team-scoped.
 */
class SelfAttendanceApprovalTest extends TestCase
{
    use RefreshDatabase;

    private function guard(): string
    {
        return config('backpack.base.guard', 'backpack');
    }

    private function user(string $name): User
    {
        return User::create([
            'name'     => $name,
            'email'    => str($name)->slug() . uniqid() . '@example.test',
            'password' => bcrypt('secret'),
        ]);
    }

    private function pendingPresence(User $user): Presence
    {
        return Presence::create([
            'user_id'         => $user->id,
            'in'              => now()->format('Y-m-d H:i:s'),
            'source'          => 'camera',
            'outside'         => true,
            'approval_status' => 'pending',
            'lat'             => '-6.30', 'lng' => '106.90',
        ]);
    }

    public function test_service_approve_transitions_and_records_approver(): void
    {
        $emp = $this->user('Emp One');
        $mgr = $this->user('Mgr One');
        $p = $this->pendingPresence($emp);

        app(PresenceService::class)->approve($p, $mgr, 'ok lapangan');

        $p->refresh();
        $this->assertEquals('approved', $p->approval_status);
        $this->assertEquals($mgr->id, $p->approved_by);
        $this->assertEquals('ok lapangan', $p->approval_note);
    }

    public function test_service_reject_keeps_record_but_marks_rejected(): void
    {
        $emp = $this->user('Emp Two');
        $mgr = $this->user('Mgr Two');
        $p = $this->pendingPresence($emp);

        app(PresenceService::class)->reject($p, $mgr, 'lokasi tidak sesuai');

        $p->refresh();
        $this->assertEquals('rejected', $p->approval_status);
        $this->assertEquals($mgr->id, $p->approved_by);
        // Record is NOT deleted — audit trail.
        $this->assertDatabaseHas('presences', ['id' => $p->id]);
    }

    public function test_approve_is_idempotent(): void
    {
        $emp = $this->user('Emp Three');
        $mgr1 = $this->user('Mgr Three');
        $mgr2 = $this->user('Mgr Four');
        $p = $this->pendingPresence($emp);

        app(PresenceService::class)->approve($p, $mgr1);
        app(PresenceService::class)->approve($p, $mgr2); // second call is a no-op

        $p->refresh();
        $this->assertEquals($mgr1->id, $p->approved_by, 'Second approval must not overwrite the first.');
    }

    public function test_approve_endpoint_notifies_employee(): void
    {
        $emp = $this->user('Emp Notif');
        $p = $this->pendingPresence($emp);

        $mgr = $this->user('Mgr Notif');
        $mgr->givePermissionTo(Permission::firstOrCreate(['name' => 'presence.view', 'guard_name' => $this->guard()]));
        $mgr->givePermissionTo(Permission::firstOrCreate(['name' => 'presence.edit', 'guard_name' => $this->guard()]));
        $mgr->givePermissionTo(Permission::firstOrCreate(['name' => 'user.view_all', 'guard_name' => $this->guard()]));

        $this->actingAs($mgr, $this->guard())
            ->post(route('presence.approve', $p->id), ['note' => 'setuju'])
            ->assertRedirect(route('presence.approvals'));

        $this->assertEquals('approved', $p->refresh()->approval_status);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $emp->id,
            'type'    => 'attendance_approved',
        ]);
    }

    public function test_approvals_screen_requires_presence_edit(): void
    {
        $viewerOnly = $this->user('View Only');
        $viewerOnly->givePermissionTo(Permission::firstOrCreate(['name' => 'presence.view', 'guard_name' => $this->guard()]));

        $this->actingAs($viewerOnly, $this->guard())
            ->get(route('presence.approvals'))
            ->assertForbidden();
    }

    public function test_reject_endpoint_marks_rejected_with_reason(): void
    {
        $emp = $this->user('Emp Rej');
        $p = $this->pendingPresence($emp);

        $mgr = $this->user('Mgr Rej');
        $mgr->givePermissionTo(Permission::firstOrCreate(['name' => 'presence.view', 'guard_name' => $this->guard()]));
        $mgr->givePermissionTo(Permission::firstOrCreate(['name' => 'presence.edit', 'guard_name' => $this->guard()]));
        $mgr->givePermissionTo(Permission::firstOrCreate(['name' => 'user.view_all', 'guard_name' => $this->guard()]));

        $this->actingAs($mgr, $this->guard())
            ->post(route('presence.reject', $p->id), ['note' => 'di luar jangkauan'])
            ->assertRedirect(route('presence.approvals'));

        $p->refresh();
        $this->assertEquals('rejected', $p->approval_status);
        $this->assertEquals('di luar jangkauan', $p->approval_note);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $emp->id,
            'type'    => 'attendance_rejected',
        ]);
    }
}
