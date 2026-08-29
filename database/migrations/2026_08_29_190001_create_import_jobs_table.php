<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * UM-09 — Tabel status import background.
 *
 * Menyimpan progress & hasil setiap import karyawan supaya:
 *   - request tidak diblokir (import jalan di queue worker),
 *   - halaman status bisa polling progress (total/processed/imported/skipped),
 *   - baris gagal terekam (baris#, kolom, nilai, alasan) untuk diperbaiki &
 *     di-import ulang.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 40)->default('user'); // jenis import (user, dst)
            $table->string('original_name')->nullable();  // nama file asli yang diunggah
            $table->string('file_path')->nullable();      // path file di storage (utk worker)
            $table->string('status', 20)->default('queued'); // queued|processing|done|failed
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('processed')->default(0);
            $table->unsignedInteger('imported')->default(0);
            $table->unsignedInteger('skipped')->default(0);
            $table->json('errors')->nullable();           // [{row,column,value,reason}]
            $table->text('message')->nullable();          // pesan fatal bila status=failed
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_jobs');
    }
};
