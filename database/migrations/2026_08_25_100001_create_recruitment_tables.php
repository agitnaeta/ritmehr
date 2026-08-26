<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M09 — Recruitment pipeline.
 *
 * job_openings → applicants (per opening, moves through pipeline stages) →
 * interviews (scheduled per applicant). Hiring an applicant creates a User and
 * links back via applicants.hired_user_id (idempotent — see RecruitmentService).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_openings', function (Blueprint $table) {
            $table->id();
            $table->string('title', 150);
            $table->string('code', 30)->nullable()->unique();
            $table->unsignedBigInteger('department_id')->nullable();
            $table->unsignedBigInteger('position_id')->nullable();
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->text('description')->nullable();
            $table->unsignedInteger('vacancies')->default(1);
            $table->unsignedBigInteger('salary_min')->nullable();
            $table->unsignedBigInteger('salary_max')->nullable();
            // draft | open | closed
            $table->string('status', 20)->default('open');
            $table->date('opened_at')->nullable();
            $table->date('closed_at')->nullable();
            $table->timestamps();

            $table->foreign('department_id')->references('id')->on('departments')->nullOnDelete();
            $table->foreign('position_id')->references('id')->on('positions')->nullOnDelete();
            $table->foreign('branch_id')->references('id')->on('branches')->nullOnDelete();
        });

        Schema::create('applicants', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('job_opening_id');
            $table->string('name', 120);
            $table->string('email', 150)->nullable();
            $table->string('phone', 30)->nullable();
            // Pipeline stage: applied | screening | interview | offer | hired | rejected
            $table->string('stage', 20)->default('applied');
            $table->text('notes')->nullable();
            $table->string('cv_path')->nullable();
            $table->unsignedBigInteger('expected_salary')->nullable();
            // Set when stage becomes hired and a User is provisioned.
            $table->unsignedBigInteger('hired_user_id')->nullable();
            $table->timestamp('hired_at')->nullable();
            $table->timestamps();

            $table->foreign('job_opening_id')->references('id')->on('job_openings')->cascadeOnDelete();
            $table->foreign('hired_user_id')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('interviews', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('applicant_id');
            $table->unsignedBigInteger('interviewer_id')->nullable();
            $table->dateTime('scheduled_at');
            $table->string('location', 150)->nullable();
            $table->string('mode', 20)->default('onsite'); // onsite | online | phone
            // scheduled | done | cancelled
            $table->string('status', 20)->default('scheduled');
            $table->text('feedback')->nullable();
            $table->unsignedTinyInteger('score')->nullable(); // 1..5
            $table->timestamps();

            $table->foreign('applicant_id')->references('id')->on('applicants')->cascadeOnDelete();
            $table->foreign('interviewer_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('interviews');
        Schema::dropIfExists('applicants');
        Schema::dropIfExists('job_openings');
    }
};
