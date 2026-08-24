<?php

namespace App\Http\Controllers\Admin;

use App\Models\Branch;
use App\Models\CompanyProfile;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

class BranchCrudController extends CrudController
{
    use ListOperation;
    use CreateOperation { store as traitStore; }
    use UpdateOperation { update as traitUpdate; }
    use DeleteOperation;
    use ShowOperation;

    public function setup()
    {
        CRUD::setModel(Branch::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/branch');
        CRUD::setEntityNameStrings('cabang', 'cabang');
    }

    protected function setupListOperation()
    {
        CRUD::column('name')->label('Nama Cabang');
        CRUD::column('code')->label('Kode');
        CRUD::column('phone')->label('Telepon');

        CRUD::addColumn([
            'name'     => 'geofence',
            'label'    => 'Geofence',
            'type'     => 'closure',
            'function' => fn (Branch $e) => $e->hasGeofence()
                ? $e->lat . ', ' . $e->lng . ' (' . $e->radius_meters . ' m)'
                : 'Belum diatur',
        ]);

        CRUD::addColumn([
            'name' => 'users', 'label' => 'Karyawan',
            'type' => 'relationship_count', 'suffix' => ' orang',
        ]);

        CRUD::column('is_active')->label('Aktif')->type('boolean');
    }

    protected function setupCreateOperation()
    {
        CRUD::field('name')->label('Nama Cabang')->type('text');
        CRUD::field('code')->label('Kode')->type('text')->hint('Mis. HO, BDG, SBY.');

        CRUD::addField([
            'name' => 'company_profile_id', 'label' => 'Perusahaan', 'type' => 'select',
            'entity' => 'companyProfile', 'attribute' => 'name',
            'model' => CompanyProfile::class, 'allows_null' => true,
        ]);

        CRUD::field('address')->label('Alamat')->type('textarea');
        CRUD::field('phone')->label('Telepon')->type('text');

        CRUD::addField([
            'name' => 'lat', 'label' => 'Latitude', 'type' => 'text',
            'hint' => 'Mis. -6.2087634. Kosongkan untuk memakai koordinat global.',
            'wrapper' => ['class' => 'form-group col-md-4'],
        ]);
        CRUD::addField([
            'name' => 'lng', 'label' => 'Longitude', 'type' => 'text',
            'hint' => 'Mis. 106.845599.',
            'wrapper' => ['class' => 'form-group col-md-4'],
        ]);
        CRUD::addField([
            'name' => 'radius_meters', 'label' => 'Radius (meter)', 'type' => 'number',
            'default' => 100, 'hint' => 'Batas jarak absensi dianggap di dalam kantor.',
            'wrapper' => ['class' => 'form-group col-md-4'],
        ]);

        CRUD::field('is_active')->label('Aktif')->type('checkbox')->default(true);
    }

    protected function setupUpdateOperation()
    {
        $this->setupCreateOperation();
    }

    protected function setupShowOperation()
    {
        $this->setupListOperation();
        CRUD::column('address')->label('Alamat');
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
            'name'          => 'required|string|max:100',
            'code'          => 'nullable|string|max:20|unique:branches,code' . ($id ? ",{$id}" : ''),
            'lat'           => 'nullable|numeric|between:-90,90',
            'lng'           => 'nullable|numeric|between:-180,180',
            'radius_meters' => 'nullable|integer|min:10|max:100000',
        ], [
            'name.required'  => 'Nama cabang wajib diisi.',
            'code.unique'    => 'Kode cabang sudah dipakai.',
            'lat.between'    => 'Latitude harus antara -90 dan 90.',
            'lng.between'    => 'Longitude harus antara -180 dan 180.',
            'radius_meters.min' => 'Radius minimal 10 meter.',
        ]);
    }
}
