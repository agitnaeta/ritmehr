<?php

namespace App\Http\Controllers\Admin;

use App\Models\Branch;
use App\Models\Department;
use App\Models\JobOpening;
use App\Models\Position;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

class JobOpeningCrudController extends CrudController
{
    use ListOperation;
    use CreateOperation { store as traitStore; }
    use UpdateOperation { update as traitUpdate; }
    use DeleteOperation;
    use ShowOperation;

    public function setup()
    {
        CRUD::setModel(JobOpening::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/job-opening');
        CRUD::setEntityNameStrings('lowongan', 'lowongan');

        if (! backpack_user()->can('recruitment.edit')) {
            CRUD::denyAccess(['create', 'update', 'delete']);
        }
    }

    protected function setupListOperation()
    {
        CRUD::column('title')->label('Posisi');
        CRUD::column('code')->label('Kode');

        CRUD::addColumn([
            'name' => 'department_id', 'label' => 'Departemen', 'type' => 'select',
            'entity' => 'department', 'attribute' => 'name', 'model' => Department::class,
        ]);

        CRUD::addColumn([
            'name' => 'vacancies', 'label' => 'Lowongan', 'type' => 'closure',
            'function' => fn (JobOpening $e) => $e->remainingVacancies() . ' / ' . $e->vacancies,
        ]);

        CRUD::addColumn([
            'name' => 'applicants', 'label' => 'Pelamar',
            'type' => 'relationship_count', 'suffix' => ' orang',
        ]);

        CRUD::addColumn([
            'name' => 'status', 'label' => 'Status', 'type' => 'closure',
            'function' => fn (JobOpening $e) => ucfirst($e->status),
        ]);
    }

    protected function setupCreateOperation()
    {
        CRUD::field('title')->label('Nama Posisi')->type('text');
        CRUD::field('code')->label('Kode')->type('text')->hint('Mis. IT-BE-01. Opsional.');

        CRUD::addField([
            'name' => 'department_id', 'label' => 'Departemen', 'type' => 'select',
            'entity' => 'department', 'attribute' => 'name', 'model' => Department::class,
            'allows_null' => true, 'wrapper' => ['class' => 'form-group col-md-4'],
        ]);
        CRUD::addField([
            'name' => 'position_id', 'label' => 'Jabatan', 'type' => 'select',
            'entity' => 'position', 'attribute' => 'name', 'model' => Position::class,
            'allows_null' => true, 'wrapper' => ['class' => 'form-group col-md-4'],
        ]);
        CRUD::addField([
            'name' => 'branch_id', 'label' => 'Cabang', 'type' => 'select',
            'entity' => 'branch', 'attribute' => 'name', 'model' => Branch::class,
            'allows_null' => true, 'wrapper' => ['class' => 'form-group col-md-4'],
        ]);

        CRUD::field('description')->label('Deskripsi')->type('textarea');

        CRUD::addField([
            'name' => 'vacancies', 'label' => 'Jumlah Lowongan', 'type' => 'number',
            'default' => 1, 'wrapper' => ['class' => 'form-group col-md-4'],
        ]);
        CRUD::addField([
            'name' => 'salary_min', 'label' => 'Gaji Minimum', 'type' => 'number',
            'wrapper' => ['class' => 'form-group col-md-4'], 'hint' => 'Opsional.',
        ]);
        CRUD::addField([
            'name' => 'salary_max', 'label' => 'Gaji Maksimum', 'type' => 'number',
            'wrapper' => ['class' => 'form-group col-md-4'], 'hint' => 'Opsional.',
        ]);

        CRUD::addField([
            'name' => 'status', 'label' => 'Status', 'type' => 'select_from_array',
            'options' => [
                JobOpening::STATUS_DRAFT => 'Draft',
                JobOpening::STATUS_OPEN => 'Dibuka',
                JobOpening::STATUS_CLOSED => 'Ditutup',
            ],
            'allows_null' => false, 'default' => JobOpening::STATUS_OPEN,
            'wrapper' => ['class' => 'form-group col-md-6'],
        ]);
        CRUD::addField([
            'name' => 'is_published', 'label' => 'Publikasikan di Portal Karir', 'type' => 'checkbox',
            'hint' => 'Centang agar lowongan tampil publik di /karir dan bisa dilamar kandidat.',
            'wrapper' => ['class' => 'form-group col-md-6'],
        ]);
        CRUD::addField([
            'name' => 'opened_at', 'label' => 'Tanggal Dibuka', 'type' => 'date',
            'wrapper' => ['class' => 'form-group col-md-3'],
        ]);
        CRUD::addField([
            'name' => 'closed_at', 'label' => 'Tanggal Ditutup', 'type' => 'date',
            'wrapper' => ['class' => 'form-group col-md-3'],
        ]);

        // ── Kriteria terstruktur (M17) — dipakai kandidat & AI matching ──
        CRUD::addField([
            'name' => 'required_skills', 'label' => 'Keahlian Wajib (pisahkan dengan koma)',
            'type' => 'textarea', 'attributes' => ['rows' => 2],
            'hint' => 'Mis. Laravel, MySQL, REST API. Dipisah koma.',
            'wrapper' => ['class' => 'form-group col-md-12'],
        ]);
        CRUD::addField([
            'name' => 'min_experience_years', 'label' => 'Min. Pengalaman (tahun)', 'type' => 'number',
            'wrapper' => ['class' => 'form-group col-md-4'],
        ]);
        CRUD::addField([
            'name' => 'education_min', 'label' => 'Pendidikan Minimal', 'type' => 'text',
            'hint' => 'Mis. S1, D3, SMA.', 'wrapper' => ['class' => 'form-group col-md-4'],
        ]);
        // ── Rubrik penilaian AI (M17-4b) — prompt bebas HR ──
        CRUD::addField([
            'name' => 'scoring_prompt', 'label' => 'Rubrik Penilaian AI (prompt bebas)',
            'type' => 'textarea', 'attributes' => ['rows' => 5],
            'hint' => 'Instruksi bebas untuk AI menilai kandidat. Mis. "Cari yang pernah memimpin tim ≥2 orang, '
                    . 'pengalaman Laravel di startup, bersedia WFO Jakarta. Kurangi nilai jika sering pindah kerja <1 tahun." '
                    . 'Jangan cantumkan usia/gender/agama/ras.',
            'wrapper' => ['class' => 'form-group col-md-12'],
        ]);
    }

    protected function setupUpdateOperation()
    {
        $this->setupCreateOperation();
    }

    protected function setupShowOperation()
    {
        $this->setupListOperation();
        CRUD::column('description')->label('Deskripsi');
        CRUD::addColumn([
            'name' => 'salary_range', 'label' => 'Rentang Gaji', 'type' => 'closure',
            'function' => fn (JobOpening $e) => $e->salaryRangeLabel(),
        ]);
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
        $id = request()->input('id');

        request()->validate([
            'title'      => 'required|string|max:150',
            'code'       => 'nullable|string|max:30|unique:job_openings,code' . ($id ? ",{$id}" : ''),
            'vacancies'  => 'required|integer|min:1|max:1000',
            'salary_min' => 'nullable|integer|min:0',
            'salary_max' => 'nullable|integer|min:0|gte:salary_min',
            'status'     => 'required|in:draft,open,closed',
        ], [
            'title.required'    => 'Nama posisi wajib diisi.',
            'code.unique'       => 'Kode lowongan sudah dipakai.',
            'vacancies.min'     => 'Jumlah lowongan minimal 1.',
            'salary_max.gte'    => 'Gaji maksimum tidak boleh lebih kecil dari minimum.',
        ]);
    }
}
