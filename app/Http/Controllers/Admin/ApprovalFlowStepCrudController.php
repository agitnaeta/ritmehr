<?php

namespace App\Http\Controllers\Admin;

use App\Models\ApprovalFlow;
use App\Models\ApprovalFlowStep;
use App\Models\User;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
use App\Models\Role;
use Illuminate\Validation\Rule;

class ApprovalFlowStepCrudController extends CrudController
{
    use ListOperation;
    use CreateOperation { store as traitStore; }
    use UpdateOperation { update as traitUpdate; }
    use DeleteOperation;

    public function setup()
    {
        CRUD::setModel(ApprovalFlowStep::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/approval-flow-step');
        CRUD::setEntityNameStrings('step persetujuan', 'step persetujuan');

        if (! backpack_user()->can('approval.configure')) {
            abort(403, 'Anda tidak berhak mengubah alur persetujuan.');
        }
    }

    protected function setupListOperation()
    {
        CRUD::addColumn([
            'name'      => 'approval_flow_id',
            'label'     => 'Alur',
            'type'      => 'select',
            'entity'    => 'approvalFlow',
            'attribute' => 'name',
            'model'     => ApprovalFlow::class,
        ]);
        CRUD::column('step_order')->label('Urutan');
        CRUD::addColumn([
            'name'  => 'approver',
            'label' => 'Approver',
            'type'  => 'closure',
            'function' => fn (ApprovalFlowStep $entry) => $entry->describe(),
        ]);

        $this->crud->orderBy('approval_flow_id')->orderBy('step_order');
    }

    protected function setupCreateOperation()
    {
        CRUD::addField([
            'name'      => 'approval_flow_id',
            'label'     => 'Alur',
            'type'      => 'select',
            'entity'    => 'approvalFlow',
            'attribute' => 'name',
            'model'     => ApprovalFlow::class,
        ]);

        CRUD::addField([
            'name'  => 'step_order',
            'label' => 'Urutan Step',
            'type'  => 'number',
            'hint'  => 'Mulai dari 1, berurutan tanpa lompatan.',
        ]);

        CRUD::addField([
            'name'    => 'approver_type',
            'label'   => 'Tipe Approver',
            'type'    => 'select_from_array',
            'options' => [
                ApprovalFlowStep::TYPE_MANAGER       => 'Atasan langsung pemohon',
                ApprovalFlowStep::TYPE_ROLE          => 'Berdasarkan role',
                ApprovalFlowStep::TYPE_SPECIFIC_USER => 'User tertentu',
            ],
            'allows_null' => false,
        ]);

        CRUD::addField([
            'name'      => 'approver_role_id',
            'label'     => 'Role Approver',
            'type'      => 'select',
            'entity'    => 'approverRole',
            'attribute' => 'name',
            'model'     => Role::class,
            'hint'      => 'Diisi hanya jika tipe = Berdasarkan role.',
        ]);

        CRUD::addField([
            'name'      => 'approver_user_id',
            'label'     => 'User Approver',
            'type'      => 'select',
            'entity'    => 'approverUser',
            'attribute' => 'name',
            'model'     => User::class,
            'hint'      => 'Diisi hanya jika tipe = User tertentu.',
        ]);
    }

    protected function setupUpdateOperation()
    {
        $this->setupCreateOperation();
    }

    /**
     * Keep the approver_* columns consistent with the chosen type, so a step
     * can't carry a stale role id after being switched to manager-based.
     */
    protected function normaliseApproverColumns(): void
    {
        $type = request()->input('approver_type');

        if ($type !== ApprovalFlowStep::TYPE_ROLE) {
            request()->merge(['approver_role_id' => null]);
        }

        if ($type !== ApprovalFlowStep::TYPE_SPECIFIC_USER) {
            request()->merge(['approver_user_id' => null]);
        }

        if ($type === ApprovalFlowStep::TYPE_ROLE && ! request()->input('approver_role_id')) {
            abort(422, 'Role approver wajib dipilih untuk tipe "Berdasarkan role".');
        }

        if ($type === ApprovalFlowStep::TYPE_SPECIFIC_USER && ! request()->input('approver_user_id')) {
            abort(422, 'User approver wajib dipilih untuk tipe "User tertentu".');
        }
    }

    public function store()
    {
        $this->validatePayload();
        $this->normaliseApproverColumns();
        $response = $this->traitStore();
        $this->syncFlowStepCount();

        return $response;
    }

    public function update()
    {
        $this->validatePayload();
        $this->normaliseApproverColumns();
        $response = $this->traitUpdate();
        $this->syncFlowStepCount();

        return $response;
    }

    private function validatePayload(): void
    {
        $id     = request()->input('id');
        $flowId = request()->input('approval_flow_id');

        request()->validate([
            'approval_flow_id' => 'required|exists:approval_flows,id',
            'step_order'       => [
                'required', 'integer', 'min:1',
                Rule::unique('approval_flow_steps', 'step_order')
                    ->where(fn ($q) => $q->where('approval_flow_id', $flowId))
                    ->ignore($id),
            ],
            'approver_type' => 'required|in:' . implode(',', [
                ApprovalFlowStep::TYPE_MANAGER,
                ApprovalFlowStep::TYPE_ROLE,
                ApprovalFlowStep::TYPE_SPECIFIC_USER,
            ]),
            // Role dan user hanya wajib pada tipe yang memakainya.
            'approver_role_id' => 'nullable|exists:roles,id|required_if:approver_type,' . ApprovalFlowStep::TYPE_ROLE,
            'approver_user_id' => 'nullable|exists:users,id|required_if:approver_type,' . ApprovalFlowStep::TYPE_SPECIFIC_USER,
        ], [
            'approval_flow_id.required'    => 'Alur persetujuan wajib dipilih.',
            'step_order.required'          => 'Urutan step wajib diisi.',
            'step_order.min'               => 'Urutan step dimulai dari 1.',
            'step_order.unique'            => 'Urutan step ini sudah dipakai pada alur tersebut.',
            'approver_type.required'       => 'Tipe approver wajib dipilih.',
            'approver_role_id.required_if' => 'Role approver wajib dipilih untuk tipe berdasarkan role.',
            'approver_user_id.required_if' => 'User approver wajib dipilih untuk tipe user tertentu.',
        ]);
    }

    private function syncFlowStepCount(): void
    {
        $flowId = request()->input('approval_flow_id');

        if ($flow = ApprovalFlow::find($flowId)) {
            $flow->update(['steps' => $flow->flowSteps()->count()]);
        }
    }
}
