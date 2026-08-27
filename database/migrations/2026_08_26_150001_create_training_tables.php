<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M11 — Training & Development (mini-LMS internal).
 *
 * trainings (kursus) → training_materials (bab/materi urut, lampiran + video) →
 * training_questions (kuis pilihan ganda, 1 set per pelatihan) →
 * training_enrollments (peserta + hasil auto-grade + sertifikat).
 *
 * Nilai = benar × (100 ÷ jumlah soal). Skor ≥ passing_score → passed.
 * Batas percobaan = max_attempts (default 3); habis → locked (reset oleh HR).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trainings', function (Blueprint $table) {
            $table->id();
            $table->string('title', 160);
            $table->text('description')->nullable();
            $table->unsignedBigInteger('trainer_id')->nullable(); // pelatih (users)
            $table->string('category', 80)->nullable();
            $table->unsignedTinyInteger('passing_score')->default(70); // KKM 0..100
            $table->unsignedTinyInteger('max_attempts')->default(3);
            // draft | published | archived
            $table->string('status', 20)->default('draft');
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->foreign('trainer_id')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('training_materials', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('training_id');
            $table->unsignedInteger('position')->default(0);
            $table->string('title', 160);
            $table->longText('content')->nullable();
            $table->string('attachment_path')->nullable(); // via StorageManager (M16)
            $table->string('video_url')->nullable();        // YouTube embed
            $table->timestamps();

            $table->foreign('training_id')->references('id')->on('trainings')->cascadeOnDelete();
        });

        Schema::create('training_questions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('training_id');
            $table->unsignedInteger('position')->default(0);
            $table->text('question');
            $table->string('option_a', 500);
            $table->string('option_b', 500);
            $table->string('option_c', 500)->nullable();
            $table->string('option_d', 500)->nullable();
            $table->char('correct_option', 1)->default('a'); // a|b|c|d
            $table->timestamps();

            $table->foreign('training_id')->references('id')->on('trainings')->cascadeOnDelete();
        });

        Schema::create('training_enrollments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('training_id');
            $table->unsignedBigInteger('user_id');
            // enrolled | passed | failed | locked
            $table->string('status', 20)->default('enrolled');
            $table->unsignedTinyInteger('score')->nullable(); // 0..100 (attempt terakhir)
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('passed_at')->nullable();
            $table->string('certificate_no', 40)->nullable();
            $table->timestamp('certificate_issued_at')->nullable();
            $table->timestamps();

            $table->foreign('training_id')->references('id')->on('trainings')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unique(['training_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_enrollments');
        Schema::dropIfExists('training_questions');
        Schema::dropIfExists('training_materials');
        Schema::dropIfExists('trainings');
    }
};
