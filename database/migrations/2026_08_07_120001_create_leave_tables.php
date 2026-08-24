<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('code', 20)->unique();
            $table->boolean('is_paid')->default(true);
            $table->integer('default_quota')->nullable();      // null = unlimited (e.g. sick leave)
            $table->integer('max_consecutive_days')->nullable();
            $table->boolean('requires_attachment')->default(false);
            $table->boolean('is_active')->default(true);
            $table->string('color', 7)->default('#3498db');
            $table->timestamps();
        });

        Schema::create('leave_balances', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('leave_type_id');
            $table->integer('year');
            $table->integer('quota')->default(0);
            $table->integer('used')->default(0);
            $table->integer('carry_over')->default(0);
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('leave_type_id')->references('id')->on('leave_types')->cascadeOnDelete();

            $table->unique(['user_id', 'leave_type_id', 'year']);
        });

        // Carry-over is genuinely available to spend, so it belongs in the
        // remaining figure — the original plan's `quota - used` would silently
        // discard it.
        DB::statement(
            'ALTER TABLE leave_balances
             ADD COLUMN remaining INT AS (quota + carry_over - used) STORED'
        );

        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('leave_type_id');
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('total_days', 5, 1)->default(0);   // .5 supports half days
            $table->text('reason')->nullable();
            $table->string('attachment')->nullable();
            $table->enum('status', ['draft', 'pending', 'approved', 'rejected', 'cancelled'])
                  ->default('draft');
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('leave_type_id')->references('id')->on('leave_types')->restrictOnDelete();
            $table->foreign('approved_by')->references('id')->on('users')->nullOnDelete();

            $table->index(['user_id', 'status']);
            $table->index(['start_date', 'end_date']);
        });

        Schema::create('leave_request_dates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('leave_request_id');
            $table->date('date');
            $table->decimal('day_value', 2, 1)->default(1.0);
            $table->timestamps();

            $table->foreign('leave_request_id')
                  ->references('id')->on('leave_requests')
                  ->cascadeOnDelete();

            $table->unique(['leave_request_id', 'date']);
            $table->index('date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_request_dates');
        Schema::dropIfExists('leave_requests');
        Schema::dropIfExists('leave_balances');
        Schema::dropIfExists('leave_types');
    }
};
