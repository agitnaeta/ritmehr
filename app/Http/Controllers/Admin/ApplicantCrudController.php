<?php

namespace App\Http\Controllers\Admin;

use App\Models\Applicant;
use App\Models\JobOpening;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

class ApplicantCrudController extends CrudController
{
    use ListOperation;
    use CreateOperation { store as traitStore; }
    use UpdateOperation { update as traitUpdate; }
    use DeleteOperation;
    use ShowOperation;

    public function setup()
    {
        CRUD::setModel(Applicant::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/applicant');
        CRUD::setEntityNameStrings('pelamar', 'pelamar');

        if (! backpack_user()->can('recruitment.edit')) {
            CRUD::denyAccess(['create', 'update', 'delete']);
        }
    }

    protected function setupListOperation()
    {
        // M17 — global search also matches extracted CV text. Backpack's global
        // search only spans registered columns, so we attach custom searchLogic
        // to the name column that additionally LIKE-matches cv_text.
        CRUD::addColumn([
            'name'  => 'name',
            'label' => 'Nama',
            'searchLogic' => function ($query, $column, $searchTerm) {
                $query->orWhere('name', 'like', "%{$searchTerm}%")
                      ->orWhere('email', 'like', "%{$searchTerm}%")
                      ->orWhere('cv_text', 'like', "%{$searchTerm}%");
            },
        ]);

        CRUD::addColumn([
            'name' => 'job_opening_id', 'label' => 'Lowongan', 'type' => 'select',
            'entity' => 'jobOpening', 'attribute' => 'title', 'model' => JobOpening::class,
        ]);

        CRUD::column('email')->label('Email');
        CRUD::column('phone')->label('Telepon');

        CRUD::addColumn([
            'name' => 'stage', 'label' => 'Tahap', 'type' => 'closure',
            'function' => fn (Applicant $e) => $e->stageLabel(),
        ]);

        // M17-3/4 — AI match score (null until scored). Sortable so HR can rank.
        CRUD::addColumn([
            'name' => 'ai_score', 'label' => 'Skor AI',
            'type' => 'closure',
            'function' => fn (Applicant $e) => $e->ai_score !== null
                ? number_format($e->ai_score, 0) . '/100'
                : ($e->vector_score !== null ? '~' . number_format($e->vector_score, 0) : '—'),
        ]);

        // Default sort: best AI score first, then newest.
        CRUD::orderBy('ai_score', 'desc')->orderBy('created_at', 'desc');
    }

    protected function setupCreateOperation()
    {
        CRUD::addField([
            'name' => 'job_opening_id', 'label' => 'Lowongan', 'type' => 'select',
            'entity' => 'jobOpening', 'attribute' => 'title', 'model' => JobOpening::class,
            'allows_null' => false,
        ]);

        CRUD::field('name')->label('Nama Pelamar')->type('text');
        CRUD::addField([
            'name' => 'email', 'label' => 'Email', 'type' => 'email',
            'wrapper' => ['class' => 'form-group col-md-6'],
        ]);
        CRUD::addField([
            'name' => 'phone', 'label' => 'Telepon', 'type' => 'text',
            'wrapper' => ['class' => 'form-group col-md-6'],
        ]);

        CRUD::addField([
            'name' => 'stage', 'label' => 'Tahap', 'type' => 'select_from_array',
            'options' => Applicant::STAGE_LABELS,
            'allows_null' => false, 'default' => Applicant::STAGE_APPLIED,
            'hint' => 'Tahap "Diterima" sebaiknya lewat tombol Terima di papan pipeline agar akun otomatis dibuat.',
            'wrapper' => ['class' => 'form-group col-md-6'],
        ]);
        CRUD::addField([
            'name' => 'expected_salary', 'label' => 'Ekspektasi Gaji', 'type' => 'number',
            'wrapper' => ['class' => 'form-group col-md-6'],
        ]);

        CRUD::addField([
            'name' => 'cv_path', 'label' => 'CV (PDF/Doc)', 'type' => 'upload',
            'withFiles' => ['disk' => 'local', 'path' => 'applicant-cv'],
        ]);

        CRUD::field('notes')->label('Catatan')->type('textarea');
    }

    protected function setupUpdateOperation()
    {
        $this->setupCreateOperation();
    }

    protected function setupShowOperation()
    {
        $this->setupListOperation();
        CRUD::column('notes')->label('Catatan');
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
            'job_opening_id' => 'required|exists:job_openings,id',
            'name'           => 'required|string|max:120',
            'email'          => 'nullable|email|max:150',
            'stage'          => 'required|in:applied,screening,interview,offer,hired,rejected',
        ], [
            'job_opening_id.required' => 'Lowongan wajib dipilih.',
            'name.required'           => 'Nama pelamar wajib diisi.',
        ]);
    }
}
