<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M18-2 — Stage history: an audit trail of applicant pipeline transitions
 * (who moved whom, when, from/to which stage). Powers the drawer timeline.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('applicant_stage_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('applicant_id')->constrained()->cascadeOnDelete();
            $table->string('from_stage')->nullable();
            $table->string('to_stage');
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('note')->nullable();
            $table->timestamps();

            $table->index(['applicant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('applicant_stage_logs');
    }
};
