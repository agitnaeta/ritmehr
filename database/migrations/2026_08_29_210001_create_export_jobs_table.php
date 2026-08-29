<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * UM-10 — Tabel status export karyawan di background (batch besar).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('export_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 40)->default('user');
            $table->string('status', 20)->default('queued'); // queued|processing|done|failed
            $table->unsignedInteger('total')->default(0);
            $table->string('file_path')->nullable();  // path XLSX hasil di storage
            $table->text('message')->nullable();
            $table->timestamp('expires_at')->nullable(); // retensi 24 jam
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('export_jobs');
    }
};
