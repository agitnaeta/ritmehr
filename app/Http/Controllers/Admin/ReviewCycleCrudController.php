<?php

namespace App\Http\Controllers\Admin;

use App\Models\ReviewCycle;
use App\Services\PerformanceService;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

class ReviewCycleCrudController extends CrudController
{
    use ListOperation;
    use CreateOperation { store as traitStore; }
    use UpdateOperation { update as traitUpdate; }
    use DeleteOperation;
    use ShowOperation;

    public function setup()
    {
        CRUD::setModel(ReviewCycle::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/review-cycle');
        CRUD::setEntityNameStrings('siklus penilaian', 'siklus penilaian');

        if (! backpack_user()->can('performance.edit')) {
            CRUD::denyAccess(['create', 'update', 'delete']);
        }
    }

    protected function setupListOperation()
    {
        CRUD::column('name')->label('Nama Siklus');
        CRUD::column('start_date')->label('Mulai')->type('date');
        CRUD::column('end_date')->label('Selesai')->type('date');
        CRUD::addColumn([
            'name' => 'status', 'label' => 'Status', 'type' => 'closure',
            'function' => fn (ReviewCycle $e) => ucfirst($e->status),
        ]);
        CRUD::addColumn([
            'name' => 'reviews', 'label' => 'Penilaian',
            'type' => 'relationship_count', 'suffix' => ' review',
        ]);
    }

    protected function setupCreateOperation()
    {
        CRUD::field('name')->label('Nama Siklus')->type('text')->hint('Mis. Penilaian Semester 1 2026.');
        CRUD::addField([
            'name' => 'start_date', 'label' => 'Tanggal Mulai', 'type' => 'date',
            'wrapper' => ['class' => 'form-group col-md-6'],
        ]);
        CRUD::addField([
            'name' => 'end_date', 'label' => 'Tanggal Selesai', 'type' => 'date',
            'wrapper' => ['class' => 'form-group col-md-6'],
        ]);
        CRUD::addField([
            'name' => 'status', 'label' => 'Status', 'type' => 'select_from_array',
            'options' => [
                ReviewCycle::STATUS_DRAFT => 'Draft',
                ReviewCycle::STATUS_ACTIVE => 'Aktif',
                ReviewCycle::STATUS_CLOSED => 'Ditutup',
            ],
            'allows_null' => false, 'default' => ReviewCycle::STATUS_DRAFT,
        ]);
        CRUD::field('description')->label('Deskripsi')->type('textarea');
    }

    protected function setupUpdateOperation()
    {
        $this->setupCreateOperation();
    }

    protected function setupShowOperation()
    {
        $this->setupListOperation();
        CRUD::column('description')->label('Deskripsi');
    }

    public function store()
    {
        $this->validatePayload();

        return $this->traitStore();
    }

    public function update()
    {
        $this->validatePayload();

        return $this->traitUpdate();
    }

    private function validatePayload(): void
    {
        request()->validate([
            'name'       => 'required|string|max:120',
            'start_date' => 'required|date',
            'end_date'   => 'required|date|after_or_equal:start_date',
            'status'     => 'required|in:draft,active,closed',
        ], [
            'name.required'      => 'Nama siklus wajib diisi.',
            'end_date.after_or_equal' => 'Tanggal selesai tidak boleh sebelum tanggal mulai.',
        ]);
    }
}
