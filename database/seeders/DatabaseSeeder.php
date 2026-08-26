<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // M15/CC-5: reference data (roles, permissions, approval flows, leave
        // types, document types, tax rates) must exist for a fresh install to be
        // usable — without roles nobody can even log in as admin.
        $this->call([
            HrisSeeder::class,
        ]);
    }
}
