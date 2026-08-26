<?php

namespace App\Http\Controllers\Admin;

use App\Models\Applicant;
use App\Models\Interview;
use App\Models\User;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

class InterviewCrudController extends CrudController
{
    use ListOperation;
    use CreateOperation { store as traitStore; }
    use UpdateOperation { update as traitUpdate; }
    use DeleteOperation;
    use ShowOperation;

    public function setup()
    {
        CRUD::setModel(Interview::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/interview');
        CRUD::setEntityNameStrings('wawancara', 'jadwal wawancara');

        if (! backpack_user()->can('recruitment.edit')) {
            CRUD::denyAccess(['create', 'update', 'delete']);
        }
    }

    protected function setupListOperation()
    {
        CRUD::addColumn([
            'name' => 'applicant_id', 'label' => 'Pelamar', 'type' => 'select',
            'entity' => 'applicant', 'attribute' => 'name', 'model' => Applicant::class,
        ]);
        CRUD::addColumn([
            'name' => 'interviewer_id', 'label' => 'Pewawancara', 'type' => 'select',
            'entity' => 'interviewer', 'attribute' => 'name', 'model' => User::class,
        ]);
        CRUD::column('scheduled_at')->label('Jadwal')->type('datetime');
        CRUD::addColumn([
            'name' => 'mode', 'label' => 'Mode', 'type' => 'closure',
            'function' => fn (Interview $e) => ucfirst($e->mode),
        ]);
        CRUD::addColumn([
            'name' => 'status', 'label' => 'Status', 'type' => 'closure',
            'function' => fn (Interview $e) => ucfirst($e->status),
        ]);
        CRUD::column('score')->label('Skor');
    }

    protected function setupCreateOperation()
    {
        CRUD::addField([
            'name' => 'applicant_id', 'label' => 'Pelamar', 'type' => 'select',
            'entity' => 'applicant', 'attribute' => 'name', 'model' => Applicant::class,
            'allows_null' => false,
        ]);
        CRUD::addField([
            'name' => 'interviewer_id', 'label' => 'Pewawancara', 'type' => 'select',
            'entity' => 'interviewer', 'attribute' => 'name', 'model' => User::class,
            'allows_null' => true,
        ]);
        CRUD::addField([
            'name' => 'scheduled_at', 'label' => 'Jadwal', 'type' => 'datetime',
            'wrapper' => ['class' => 'form-group col-md-6'],
        ]);
        CRUD::addField([
            'name' => 'mode', 'label' => 'Mode', 'type' => 'select_from_array',
            'options' => [
                Interview::MODE_ONSITE => 'Tatap Muka',
                Interview::MODE_ONLINE => 'Online',
                Interview::MODE_PHONE => 'Telepon',
            ],
            'allows_null' => false, 'default' => Interview::MODE_ONSITE,
            'wrapper' => ['class' => 'form-group col-md-6'],
        ]);
        CRUD::addField([
            'name' => 'location', 'label' => 'Lokasi / Link', 'type' => 'text',
            'hint' => 'Ruang meeting, atau tautan Zoom/Meet.',
        ]);
        CRUD::addField([
            'name' => 'status', 'label' => 'Status', 'type' => 'select_from_array',
            'options' => [
                Interview::STATUS_SCHEDULED => 'Terjadwal',
                Interview::STATUS_DONE => 'Selesai',
                Interview::STATUS_CANCELLED => 'Dibatalkan',
            ],
            'allows_null' => false, 'default' => Interview::STATUS_SCHEDULED,
            'wrapper' => ['class' => 'form-group col-md-6'],
        ]);
        CRUD::addField([
            'name' => 'score', 'label' => 'Skor (1-5)', 'type' => 'number',
            'attributes' => ['min' => 1, 'max' => 5],
            'wrapper' => ['class' => 'form-group col-md-6'],
        ]);
        CRUD::field('feedback')->label('Catatan / Hasil')->type('textarea');
    }

    protected function setupUpdateOperation()
    {
        $this->setupCreateOperation();
    }

    protected function setupShowOperation()
    {
        $this->setupListOperation();
        CRUD::column('location')->label('Lokasi / Link');
        CRUD::column('feedback')->label('Catatan / Hasil');
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
            'applicant_id' => 'required|exists:applicants,id',
            'scheduled_at' => 'required|date',
            'mode'         => 'required|in:onsite,online,phone',
            'status'       => 'required|in:scheduled,done,cancelled',
            'score'        => 'nullable|integer|min:1|max:5',
        ], [
            'applicant_id.required' => 'Pelamar wajib dipilih.',
            'scheduled_at.required' => 'Jadwal wawancara wajib diisi.',
        ]);
    }
}
