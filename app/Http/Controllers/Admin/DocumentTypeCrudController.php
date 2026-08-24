<?php

namespace App\Http\Controllers\Admin;

use App\Models\DocumentType;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

class DocumentTypeCrudController extends CrudController
{
    use ListOperation;
    use CreateOperation { store as traitStore; }
    use UpdateOperation { update as traitUpdate; }
    use DeleteOperation;

    public function setup()
    {
        CRUD::setModel(DocumentType::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/document-type');
        CRUD::setEntityNameStrings('jenis dokumen', 'jenis dokumen');
    }

    protected function setupListOperation()
    {
        CRUD::column('name')->label('Nama');
        CRUD::column('code')->label('Kode');
        CRUD::column('has_expiry')->label('Ada Masa Berlaku')->type('boolean');
        CRUD::column('is_required')->label('Wajib')->type('boolean');
        CRUD::addColumn(['name' => 'max_file_size_mb', 'label' => 'Maks. Ukuran', 'type' => 'number', 'suffix' => ' MB']);
        CRUD::column('allowed_extensions')->label('Format');
        CRUD::addColumn([
            'name' => 'documents', 'label' => 'Terunggah',
            'type' => 'relationship_count', 'suffix' => ' berkas',
        ]);
    }

    protected function setupCreateOperation()
    {
        CRUD::field('name')->label('Nama Dokumen')->type('text');
        CRUD::field('code')->label('Kode')->type('text')->hint('Mis. ktp, npwp, kontrak.');
        CRUD::field('has_expiry')->label('Punya Masa Berlaku')->type('checkbox')
            ->hint('Jika dicentang, tanggal kedaluwarsa wajib diisi saat unggah.');
        CRUD::field('is_required')->label('Wajib untuk Onboarding')->type('checkbox');
        CRUD::addField([
            'name' => 'max_file_size_mb', 'label' => 'Maks. Ukuran (MB)',
            'type' => 'number', 'default' => 5,
        ]);
        CRUD::field('allowed_extensions')->label('Format Diizinkan')->type('text')
            ->default('pdf,jpg,png')->hint('Dipisah koma, tanpa titik.');
    }

    protected function setupUpdateOperation()
    {
        $this->setupCreateOperation();
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
            'name'             => 'required|string|max:100',
            'code'             => 'required|string|max:20|unique:document_types,code' . ($id ? ",{$id}" : ''),
            'max_file_size_mb' => 'nullable|integer|min:1|max:100',
        ], [
            'name.required' => 'Nama dokumen wajib diisi.',
            'code.unique'   => 'Kode dokumen sudah dipakai.',
        ]);
    }
}
