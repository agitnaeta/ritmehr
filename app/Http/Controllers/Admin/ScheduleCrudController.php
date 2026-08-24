<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\ScheduleRequest;
use App\Models\Day;
use App\Models\Schedule;
use App\Models\ScheduleDayOff;
use App\Models\User;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Database\Factories\TranslateFactory;
use Illuminate\Http\Request;
use Prologue\Alerts\Facades\Alert;
use function Symfony\Component\Translation\t;

/**
 * Class ScheduleCrudController
 * @package App\Http\Controllers\Admin
 * @property-read \Backpack\CRUD\app\Library\CrudPanel\CrudPanel $crud
 */
class ScheduleCrudController extends CrudController
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
        CRUD::setModel(\App\Models\Schedule::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/schedule');
        CRUD::setEntityNameStrings('Jadwal', 'Jadwal');

        // Manager boleh melihat jadwal, tidak boleh mengubahnya.
        if (! backpack_user()->can('schedule.edit')) {
            CRUD::denyAccess(['create', 'update', 'delete']);
        }
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

    protected function setupShowOperation()
    {
        $this->fieldModification();
    }

    /**
     * Define what happens when the Create operation is loaded.
     *
     * @see https://backpackforlaravel.com/docs/crud-operation-create
     * @return void
     */
    protected function setupCreateOperation()
    {
        CRUD::setValidation(ScheduleRequest::class);
        CRUD::setFromDb(); // set fields from db columns.

        /**
         * Fields can be defined using the fluent syntax:
         * - CRUD::field('price')->type('number');
         */
        $this->crud->addField([
            'name' => 'day_off',
            'label' => 'Hari Libur',
            'type' => 'day_off_check',
            'option'=>Day::all(),
            'selected'=>[],
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
        CRUD::setValidation(ScheduleRequest::class);
        CRUD::setFromDb(); // set fields from db columns.

        /**
         * Fields can be defined using the fluent syntax:
         * - CRUD::field('price')->type('number');
         */

        $entry = $this->crud->getCurrentEntry();
        $dayOffs = ScheduleDayOff::where("schedule_id",$entry['id'])->get()->pluck('day');
        $this->crud->addField([
            'name' => 'day_off',
            'label' => 'Hari Libur',
            'type' => 'day_off_check',
            'option'=>Day::all(),
            'selected'=>$dayOffs->toArray(),
        ]);
        $this->fieldModification();

    }

    public function fieldModification(){


        $translate = new TranslateFactory();
        foreach($translate->schedules() as $key => $value){
            $this->crud->field($key)->label($value);
            $this->crud->column($key)->label($value);
        }
        $this->crud->removeField('created_at');
        $this->crud->removeField('updated_at');
    }


    public function store() {
        $request = $this->crud->validateRequest();
        $dayOffs = collect($request->get('day_off'));

        $schedule = Schedule::create($request->all());
        $dayOffs->map(function ($off) use($schedule){
           ScheduleDayOff::create(['day'=>$off,'schedule_id'=>$schedule->id]);
        });

        Alert::success("Berhasil menambahkan data")->flash();
        return redirect(route('schedule.index'));
    }

    public function update() {

        $request = $this->crud->validateRequest();

        $dayOffs = collect($request->get('day_off'));
        ScheduleDayOff::where("schedule_id",$request->get('id'))->delete();

        $schedule = Schedule::find($request->get('id'));
        $schedule->update($request->all());
        $dayOffs->map(function ($off) use($request){
            ScheduleDayOff::create(['day'=>$off,'schedule_id'=>$request->get('id')]);
        });

        Alert::success("Berhasil update data")->flash();
        return redirect(route('schedule.index'));
    }


    public function viewSchedule(){
        $users = User::all();
        $schedules = Schedule::all();
        return view('schedule.set',compact('users','schedules'));
    }

    public function massUpdateSchedule(Request $request){
        $userIds = $request->get('user_ids');
        $schedules = $request->get('schedule_ids');


        for ($i = 0; $i < count($userIds); $i++) {
            $user  = User::find($userIds[$i]);
            if($user){
                $user->schedule_id = $schedules[$i];
                $user->save();
            }
        }

        Alert::success("Berhasil Update data")->flash();
        return redirect(route('schedule.view.update'));
    }

}
