<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * One-shot setup for the HRIS modules. Every child seeder is idempotent, so
 * this is safe to re-run after an upgrade.
 */
class HrisSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            ApprovalFlowSeeder::class,
            LeaveTypeSeeder::class,
            DocumentTypeSeeder::class,
            TaxRateSeeder::class,
            TerRateSeeder::class,
            ChartOfAccountsSeeder::class,
        ]);

        $this->command?->info('HRIS reference data seeded.');
    }
}
