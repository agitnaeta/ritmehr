<?php

namespace App\Http\Controllers\Admin;

use App\Models\Department;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use App\Services\LeaveService;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Prologue\Alerts\Facades\Alert;

class LeaveRequestCrudController extends CrudController
{
    use ListOperation;
    use ShowOperation;
    use \App\Traits\HasSimpleFilters;

    public function __construct(private readonly LeaveService $leaveService)
    {
        parent::__construct();
    }

    public function setup()
    {
        CRUD::setModel(LeaveRequest::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/leave-request');
        CRUD::setEntityNameStrings('pengajuan cuti', 'pengajuan cuti');
        CRUD::addClause('with', ['user', 'leaveType', 'approval']);

        // Creating leave on someone else's behalf goes through the dedicated
        // form so the service's validation always runs.
        CRUD::denyAccess(['create', 'update', 'delete']);

        if (! backpack_user()->can('leave.view_all')) {
            CRUD::addClause('where', 'user_id', backpack_user()->id);
        }
    }

    protected function setupListOperation()
    {
        CRUD::column('id')->label('ID');

        CRUD::addColumn([
            'name' => 'user_id', 'label' => 'Karyawan', 'type' => 'select',
            'entity' => 'user', 'attribute' => 'name', 'model' => User::class,
        ]);

        CRUD::addColumn([
            'name' => 'leave_type_id', 'label' => 'Jenis', 'type' => 'select',
            'entity' => 'leaveType', 'attribute' => 'name', 'model' => LeaveType::class,
        ]);

        CRUD::addColumn([
            'name'     => 'period',
            'label'    => 'Periode',
            'type'     => 'closure',
            'function' => fn (LeaveRequest $e) => $e->periodLabel(),
        ]);

        CRUD::column('total_days')->label('Hari');

        CRUD::addColumn([
            'name'     => 'status',
            'label'    => 'Status',
            'type'     => 'closure',
            'function' => fn (LeaveRequest $e) => $e->statusLabel(),
        ]);

        CRUD::column('created_at')->label('Diajukan');

        $this->applySimpleFilters([
            [
                'name'    => 'status',
                'label'   => 'Status',
                'type'    => 'select',
                'column'  => 'leave_requests.status',
                'options' => [
                    LeaveRequest::STATUS_PENDING   => 'Menunggu',
                    LeaveRequest::STATUS_APPROVED  => 'Disetujui',
                    LeaveRequest::STATUS_REJECTED  => 'Ditolak',
                    LeaveRequest::STATUS_CANCELLED => 'Dibatalkan',
                ],
            ],
            [
                'name'    => 'leave_type_id',
                'label'   => 'Jenis Cuti',
                'type'    => 'select',
                'options' => LeaveType::orderBy('name')->pluck('name', 'id')->toArray(),
            ],
            [
                'name'    => 'department_id',
                'label'   => 'Departemen',
                'type'    => 'select',
                'options' => Department::orderBy('name')->pluck('name', 'id')->toArray(),
                'apply'   => fn ($query, $value) => $query->whereHas(
                    'user', fn ($q) => $q->where('department_id', $value)
                ),
            ],
            [
                'name'  => 'from',
                'label' => 'Mulai Dari',
                'type'  => 'date',
                'apply' => fn ($query, $value) => $query->where('end_date', '>=', $value),
            ],
            [
                'name'  => 'to',
                'label' => 'Sampai',
                'type'  => 'date',
                'apply' => fn ($query, $value) => $query->where('start_date', '<=', $value),
            ],
        ]);

        $this->crud->orderBy('created_at', 'desc');
    }

    protected function setupShowOperation()
    {
        $this->setupListOperation();
        CRUD::column('reason')->label('Alasan');
        CRUD::column('rejection_reason')->label('Alasan Penolakan');
        CRUD::column('approved_at')->label('Disetujui Pada');
    }

    // ── Admin-side request form ────────────────────────────

    public function createForm()
    {
        return view('admin.leave.create', [
            'users'      => User::employed()->orderBy('name')->get(),
            'leaveTypes' => LeaveType::active()->orderBy('name')->get(),
        ]);
    }

    public function storeForm(Request $request)
    {
        $data = $request->validate([
            'user_id'       => 'required|exists:users,id',
            'leave_type_id' => 'required|exists:leave_types,id',
            'start_date'    => 'required|date',
            'end_date'      => 'required|date',
            'reason'        => 'nullable|string|max:1000',
            'attachment'    => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ], [
            'user_id.required'       => 'Karyawan wajib dipilih.',
            'leave_type_id.required' => 'Jenis cuti wajib dipilih.',
            'start_date.required'    => 'Tanggal mulai wajib diisi.',
            'end_date.required'      => 'Tanggal selesai wajib diisi.',
        ]);

        $path = $request->hasFile('attachment')
            ? $request->file('attachment')->store('leave-attachments', 'public')
            : null;

        try {
            $this->leaveService->requestLeave(
                User::findOrFail($data['user_id']),
                LeaveType::findOrFail($data['leave_type_id']),
                $data['start_date'],
                $data['end_date'],
                $data['reason'] ?? null,
                $path
            );
        } catch (\DomainException | \RuntimeException $e) {
            Alert::error($e->getMessage())->flash();

            return back()->withInput();
        }

        Alert::success('Pengajuan cuti dibuat dan dikirim untuk persetujuan.')->flash();

        return redirect(backpack_url('leave-request'));
    }

    public function cancel(int $id)
    {
        $leaveRequest = LeaveRequest::findOrFail($id);

        try {
            $this->leaveService->cancel($leaveRequest, backpack_user());
        } catch (\DomainException $e) {
            Alert::error($e->getMessage())->flash();

            return back();
        }

        Alert::success('Pengajuan dibatalkan.')->flash();

        return back();
    }

    // ── Calendar ───────────────────────────────────────────

    public function calendar(Request $request)
    {
        $month = $request->input('month')
            ? Carbon::parse($request->input('month') . '-01')
            : now()->startOfMonth();

        $departmentId = $request->input('department_id') ?: null;

        $entries = $this->leaveService->calendarEntries(
            $month->copy()->startOfMonth(),
            $month->copy()->endOfMonth(),
            $departmentId ? (int) $departmentId : null
        );

        // Index approved leave by date so the grid can look each day up.
        $byDate = [];

        foreach ($entries as $entry) {
            foreach ($entry->dates as $d) {
                $key = Carbon::parse($d->date)->toDateString();
                $byDate[$key][] = $entry;
            }
        }

        return view('admin.leave.calendar', [
            'month'        => $month,
            'byDate'       => $byDate,
            'departments'  => Department::orderBy('name')->get(),
            'departmentId' => $departmentId,
            'leaveTypes'   => \App\Models\LeaveType::active()->orderBy('name')->get(),
        ]);
    }

    // ── Report ─────────────────────────────────────────────

    public function report(Request $request)
    {
        $year = (int) ($request->input('year') ?: now()->year);
        $departmentId = $request->input('department_id') ?: null;

        $query = LeaveRequest::with(['user.department', 'leaveType'])
            ->approved()
            ->whereYear('start_date', $year);

        if ($departmentId) {
            $query->whereHas('user', fn ($q) => $q->where('department_id', $departmentId));
        }

        $rows = $query->get()
            ->groupBy('user_id')
            ->map(function ($requests) {
                $user = $requests->first()->user;

                return [
                    'user'       => $user,
                    'department' => $user?->department?->name ?? '—',
                    'byType'     => $requests->groupBy(fn ($r) => $r->leaveType?->name ?? '—')
                                             ->map(fn ($g) => $g->sum('total_days')),
                    'total'      => $requests->sum('total_days'),
                ];
            })
            ->sortByDesc('total');

        return view('admin.leave.report', [
            'rows'         => $rows,
            'year'         => $year,
            'departments'  => Department::orderBy('name')->get(),
            'departmentId' => $departmentId,
            'types'        => LeaveType::orderBy('name')->pluck('name'),
        ]);
    }
}
