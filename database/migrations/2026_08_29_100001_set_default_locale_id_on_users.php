<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * UM-05 — Default locale karyawan `id` (Indonesia).
 *
 * Kolom `locale` (M13) dibuat nullable tanpa default sehingga karyawan tampil
 * Bahasa "-". Selaraskan ke default aplikasi (config('app.locale') = 'id'):
 *   1. Backfill semua baris NULL → 'id'.
 *   2. Set DEFAULT 'id' di level kolom untuk record baru via SQL langsung.
 *
 * Laravel 12 mendukung perubahan skema native, tapi ALTER ... SET DEFAULT via
 * SQL mentah paling ringkas & tidak butuh doctrine/dbal. Guarded per-driver
 * agar aman di MySQL (produksi) maupun SQLite (test in-memory).
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Backfill baris lama.
        DB::table('users')->whereNull('locale')->update(['locale' => 'id']);

        // 2. Set default kolom = 'id' (driver-aware).
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement("ALTER TABLE `users` MODIFY `locale` VARCHAR(5) NOT NULL DEFAULT 'id'");
        } elseif ($driver === 'pgsql') {
            DB::statement("ALTER TABLE users ALTER COLUMN locale SET DEFAULT 'id'");
            DB::statement("ALTER TABLE users ALTER COLUMN locale SET NOT NULL");
        }
        // SQLite: tak mendukung ALTER COLUMN default; default di-handle model
        // ($attributes) + backfill di atas sudah cukup untuk lingkungan test.
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement("ALTER TABLE `users` MODIFY `locale` VARCHAR(5) NULL DEFAULT NULL");
        } elseif ($driver === 'pgsql') {
            DB::statement("ALTER TABLE users ALTER COLUMN locale DROP DEFAULT");
            DB::statement("ALTER TABLE users ALTER COLUMN locale DROP NOT NULL");
        }
    }
};
