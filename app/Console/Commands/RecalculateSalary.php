<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\Presence;
use App\Models\SalaryRecap;
use App\Services\SalaryService;

class RecalculateSalary extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'salary:recalculate';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Recalculate Salary for specific users';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $userIds = collect([
            '14',// Ahmad Saepulon 
            '12', // Risma Apriliani
            '10',//Agung Widianto
            '8',//Muhamad Farhan Fadillah
            '5' // Exka Taufikurohman
        ]);

        $recaps = SalaryRecap::where('recap_month','04-2025')->
        whereIn('user_id',$userIds)->get();

        foreach ($recaps as $recap) {
            $this->info("Update Salary for : ". $recap->user->name);
            (new SalaryService())->calculateSalaryRecap($recap);
        }
        return $this->info("Done");


    }
}
