<?php

namespace App\Http\Controllers\Admin;

use App\Models\Approval;
use App\Models\User;
use App\Services\ApprovalService;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Illuminate\Http\Request;

class ApprovalCrudController extends CrudController
{
    use ListOperation;
    use ShowOperation;
    use \App\Traits\HasSimpleFilters;

    public function setup()
    {
        CRUD::setModel(Approval::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/approval');
        CRUD::setEntityNameStrings('persetujuan', 'persetujuan');

        CRUD::denyAccess(['create', 'update', 'delete']);

        // Users without approval.view_all only ever see their own inbox and
        // the requests they raised themselves.
        if (! backpack_user()->can('approval.view_all')) {
            $this->scopeToOwnInbox();
        }
    }

    /**
     * Restrict the list to rows this user either raised or can act on.
     */
    private function scopeToOwnInbox(): void
    {
        $user = backpack_user();
        $actionable = app(ApprovalService::class)
            ->getPendingForUser($user)
            ->pluck('id')
            ->all();

        // Nested so the OR stays self-contained — otherwise a status filter
        // would bind as `mine OR (actionable AND status=X)`.
        CRUD::addClause(function ($query) use ($user, $actionable) {
            $query->where(function ($q) use ($user, $actionable) {
                $q->where('approvals.requested_by', $user->id)
                  ->orWhereIn('approvals.id', $actionable ?: [0]);
            });
        });
    }

    protected function setupListOperation()
    {
        CRUD::column('id')->label('ID');

        CRUD::addColumn([
            'name'     => 'module',
            'label'    => 'Modul',
            'type'     => 'closure',
            'function' => fn (Approval $e) => $e->approvalFlow?->module ?? '—',
        ]);

        CRUD::addColumn([
            'name'     => 'approvable',
            'label'    => 'Dokumen',
            'type'     => 'closure',
            'function' => fn (Approval $e) => class_basename($e->approvable_type) . ' #' . $e->approvable_id,
        ]);

        CRUD::addColumn([
            'name'      => 'requested_by',
            'label'     => 'Pemohon',
            'type'      => 'select',
            'entity'    => 'requester',
            'attribute' => 'name',
            'model'     => User::class,
        ]);

        CRUD::addColumn([
            'name'     => 'progress',
            'label'    => 'Step',
            'type'     => 'closure',
            'function' => fn (Approval $e) => $e->isPending()
                ? "{$e->current_step} / " . ($e->approvalFlow?->totalSteps() ?? '?')
                : '—',
        ]);

        CRUD::addColumn([
            'name'     => 'status',
            'label'    => 'Status',
            'type'     => 'closure',
            'function' => fn (Approval $e) => $e->statusLabel(),
        ]);

        CRUD::column('created_at')->label('Diajukan');

        $this->applySimpleFilters([
            [
                'name'    => 'status',
                'label'   => 'Status',
                'type'    => 'select',
                'column'  => 'approvals.status',
                'options' => [
                    Approval::STATUS_PENDING   => 'Menunggu',
                    Approval::STATUS_APPROVED  => 'Disetujui',
                    Approval::STATUS_REJECTED  => 'Ditolak',
                    Approval::STATUS_CANCELLED => 'Dibatalkan',
                ],
            ],
        ]);

        CRUD::addButtonFromView('line', 'approval_actions', 'approval_actions', 'end');

        $this->crud->orderBy('created_at', 'desc');
    }

    protected function setupShowOperation()
    {
        $this->setupListOperation();
        CRUD::removeButton('approval_actions');
    }

    /**
     * Detail page: the record, its flow, and the full action history.
     */
    public function detail(int $id)
    {
        $approval = Approval::with([
            'approvalFlow.flowSteps.approverRole',
            'approvalFlow.flowSteps.approverUser',
            'requester',
            'actions.actor',
            'approvable',
        ])->findOrFail($id);

        $this->authoriseView($approval);

        return view('admin.approval.detail', [
            'approval'  => $approval,
            'crudRoute' => config('backpack.base.route_prefix') . '/approval',
            'canAct'    => $approval->canBeActedOnBy(backpack_user()),
            'canCancel' => $approval->isPending()
                && (int) $approval->requested_by === (int) backpack_user()->id,
        ]);
    }

    public function approve(Request $request, int $id)
    {
        $approval = Approval::findOrFail($id);

        try {
            app(ApprovalService::class)->approve(
                $approval,
                backpack_user(),
                $request->input('notes')
            );
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Persetujuan berhasil disimpan.');
    }

    public function reject(Request $request, int $id)
    {
        $request->validate(
            ['reason' => 'required|string|min:3'],
            ['reason.required' => 'Alasan penolakan wajib diisi.']
        );

        $approval = Approval::findOrFail($id);

        try {
            app(ApprovalService::class)->reject(
                $approval,
                backpack_user(),
                $request->input('reason')
            );
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Pengajuan ditolak.');
    }

    public function cancel(int $id)
    {
        $approval = Approval::findOrFail($id);

        try {
            app(ApprovalService::class)->cancel($approval, backpack_user());
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Pengajuan dibatalkan.');
    }

    private function authoriseView(Approval $approval): void
    {
        $user = backpack_user();

        $allowed = $user->can('approval.view_all')
            || (int) $approval->requested_by === (int) $user->id
            || $approval->canBeActedOnBy($user);

        abort_unless($allowed, 403, 'Anda tidak berhak melihat pengajuan ini.');
    }
}
