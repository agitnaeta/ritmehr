<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M10 — Performance Management.
 *
 * review_cycles (periode penilaian) → kpis (katalog KPI + bobot) →
 * reviews (satu per karyawan per siklus, self + manager) →
 * review_items (skor per KPI: self & manager, 1..5).
 *
 * Final score = rata-rata TERBOBOT skor manager per KPI (dihitung di
 * PerformanceService saat finalisasi).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('review_cycles', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->date('start_date');
            $table->date('end_date');
            // draft | active | closed
            $table->string('status', 20)->default('draft');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('kpis', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->text('description')->nullable();
            // Relative weight for the weighted average (default equal).
            $table->unsignedInteger('weight')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('review_cycle_id');
            $table->unsignedBigInteger('user_id');       // yang dinilai
            $table->unsignedBigInteger('reviewer_id')->nullable(); // manager penilai
            // pending | self_submitted | manager_submitted | finalized
            $table->string('status', 20)->default('pending');
            $table->text('self_comment')->nullable();
            $table->text('manager_comment')->nullable();
            $table->decimal('final_score', 4, 2)->nullable(); // 0..5
            $table->timestamp('self_submitted_at')->nullable();
            $table->timestamp('finalized_at')->nullable();
            $table->timestamps();

            $table->foreign('review_cycle_id')->references('id')->on('review_cycles')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('reviewer_id')->references('id')->on('users')->nullOnDelete();
            $table->unique(['review_cycle_id', 'user_id']);
        });

        Schema::create('review_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('review_id');
            $table->unsignedBigInteger('kpi_id');
            $table->unsignedTinyInteger('self_score')->nullable();    // 1..5
            $table->unsignedTinyInteger('manager_score')->nullable(); // 1..5
            $table->unsignedInteger('weight')->default(1); // snapshot of KPI weight at review time
            $table->timestamps();

            $table->foreign('review_id')->references('id')->on('reviews')->cascadeOnDelete();
            $table->foreign('kpi_id')->references('id')->on('kpis')->cascadeOnDelete();
            $table->unique(['review_id', 'kpi_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('review_items');
        Schema::dropIfExists('reviews');
        Schema::dropIfExists('kpis');
        Schema::dropIfExists('review_cycles');
    }
};
