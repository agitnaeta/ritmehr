<?php

namespace App\Console\Commands;

use Database\Seeders\HrisSeeder;
use Illuminate\Console\Command;

/**
 * M15 — One-shot, idempotent installer for HRIS reference data.
 *
 * Safe to re-run after an upgrade: every child seeder uses firstOrCreate /
 * updateOrCreate. Gives operators a single obvious command instead of having to
 * know the seeder class name.
 */
class InstallHris extends Command
{
    protected $signature = 'hris:install';

    protected $description = 'Seed/refresh HRIS reference data (roles, permissions, approval flows, leave & document types, tax rates)';

    public function handle(): int
    {
        $this->info('Menyiapkan data referensi HRIS...');
        $this->call('db:seed', ['--class' => HrisSeeder::class, '--force' => true]);
        $this->info('Selesai. Sistem siap dipakai.');

        return self::SUCCESS;
    }
}
