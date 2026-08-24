<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approvals', function (Blueprint $table) {
            $table->id();
            $table->string('approvable_type', 100);
            $table->unsignedBigInteger('approvable_id');
            $table->foreignId('approval_flow_id')
                  ->constrained('approval_flows')
                  ->restrictOnDelete();
            $table->unsignedInteger('current_step')->default(1);
            $table->enum('status', ['pending', 'approved', 'rejected', 'cancelled'])
                  ->default('pending');
            $table->unsignedBigInteger('requested_by');
            $table->timestamps();

            $table->foreign('requested_by')
                  ->references('id')->on('users')
                  ->cascadeOnDelete();

            // One live approval per record — enforced at the DB level so a
            // double-submit can't create two competing approval chains.
            $table->unique(['approvable_type', 'approvable_id'], 'approvals_approvable_unique');
            $table->index('status');
            $table->index('requested_by');
        });

        Schema::create('approval_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('approval_id')
                  ->constrained('approvals')
                  ->cascadeOnDelete();
            $table->unsignedInteger('step_order');
            $table->enum('action', ['approve', 'reject']);
            $table->unsignedBigInteger('acted_by');
            $table->text('notes')->nullable();
            $table->timestamp('acted_at');
            $table->timestamp('created_at')->nullable();

            $table->foreign('acted_by')
                  ->references('id')->on('users')
                  ->cascadeOnDelete();

            $table->index(['approval_id', 'step_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_actions');
        Schema::dropIfExists('approvals');
    }
};
