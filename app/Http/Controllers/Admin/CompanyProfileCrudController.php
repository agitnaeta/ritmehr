<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\CompanyProfileRequest;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Database\Factories\TranslateFactory;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\Storage;

/**
 * Class CompanyProfileCrudController
 * @package App\Http\Controllers\Admin
 * @property-read \Backpack\CRUD\app\Library\CrudPanel\CrudPanel $crud
 */
class CompanyProfileCrudController extends CrudController
{
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;

    /**
     * Configure the CrudPanel object. Apply settings to all operations.
     *
     * @return void
     */
    public function setup()
    {
        CRUD::setModel(\App\Models\CompanyProfile::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/company-profile');
        CRUD::setEntityNameStrings('Profil Perusahaan', 'Profil Perusahaan');
    }

    public function autoSetupShowOperation()
    {
        CRUD::setFromDb();
        CRUD::column('image')->remove();
        $this->crud->column([
            'name'=>'image',
            'label'=>'Logo Perusahaan',
            'type'=>'custom_html',
            'value'=>function($entry){
                 $path = "public/$entry->image";
                 $storage = Storage::url($path);
                 return "<img class='company-logo' src='$storage' />";
            }

        ]);

        CRUD::column('id_card')->remove();
        $this->crud->column([
            'name'=>'id_card',
            'label'=>'Background Id Card',
            'type'=>'custom_html',
            'value'=>function($entry){
                $path = "public/$entry->id_card";
                $storage = Storage::url($path);
                return "<img width='300' height='470' src='$storage' />";
            }

        ]);
    }

    /**
     * Define what happens when the List operation is loaded.
     *
     * @see  https://backpackforlaravel.com/docs/crud-operation-list-entries
     * @return void
     */
    protected function setupListOperation()
    {
        CRUD::setFromDb(); // set columns from db columns.

        /**
         * Columns can be defined using the fluent syntax:
         * - CRUD::column('price')->type('number');
         */
        $this->fieldModification();

    }

    function fieldModification()
    {
        $translate = new TranslateFactory();
        foreach($translate->company() as $key => $value){
            $this->crud->field($key)->label($value);
            $this->crud->column($key)->label($value);
        }
        $this->crud->removeField('created_at');
        $this->crud->removeField('updated_at');

        if($this->crud->getCurrentOperation() == 'list'){
            $this->crud->removeColumn('image');
            $this->crud->removeColumn('id_card');
        }
    }

    /**
     * Define what happens when the Create operation is loaded.
     *
     * @see https://backpackforlaravel.com/docs/crud-operation-create
     * @return void
     */
    protected function setupCreateOperation()
    {
        CRUD::setValidation(CompanyProfileRequest::class);
        CRUD::setFromDb(); // set fields from db columns.

        /**
         * Fields can be defined using the fluent syntax:
         * - CRUD::field('price')->type('number');
         */

        CRUD::field('image')
            ->type('upload')
            ->withFiles([
                'disk' => 'public', // the disk where file will be stored
                'path' => 'uploads', // the path inside the disk where file will be stored
            ]);
        CRUD::field('id_card')
            ->type('upload')
            ->hint('Ukuran terbaik 300px X 470px')
            ->withFiles([
                'disk' => 'public', // the disk where file will be stored
                'path' => 'uploads', // the path inside the disk where file will be stored
            ]);
        $this->fieldModification();
    }

    /**
     * Define what happens when the Update operation is loaded.
     *
     * @see https://backpackforlaravel.com/docs/crud-operation-update
     * @return void
     */
    protected function setupUpdateOperation()
    {
        CRUD::setFromDb(); // set fields from db columns.

        /**
         * Fields can be defined using the fluent syntax:
         * - CRUD::field('price')->type('number');
         */

        CRUD::field('image')
            ->type('upload')
            ->withFiles([
                'disk' => 'public', // the disk where file will be stored
                'path' => 'uploads', // the path inside the disk where file will be stored
            ]);
        CRUD::field('id_card')
            ->type('upload')
            ->hint('Ukuran terbaik 300px X 470px')
            ->withFiles([
                'disk' => 'public', // the disk where file will be stored
                'path' => 'uploads', // the path inside the disk where file will be stored
            ]);
        $this->fieldModification();
        CRUD::setValidation((new CompanyProfileRequest())->rulesUpdate());
    }
}
