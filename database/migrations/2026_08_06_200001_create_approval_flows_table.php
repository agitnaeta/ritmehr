<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_flows', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('module', 50);
            $table->unsignedInteger('steps')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('module');
        });

        $rolesTable = config('permission.table_names.roles', 'roles');

        Schema::create('approval_flow_steps', function (Blueprint $table) use ($rolesTable) {
            $table->id();
            $table->foreignId('approval_flow_id')
                  ->constrained('approval_flows')
                  ->cascadeOnDelete();
            $table->unsignedInteger('step_order');
            $table->enum('approver_type', ['role', 'manager', 'specific_user']);
            $table->unsignedBigInteger('approver_role_id')->nullable();
            $table->unsignedBigInteger('approver_user_id')->nullable();
            $table->timestamps();

            $table->foreign('approver_role_id')
                  ->references('id')->on($rolesTable)
                  ->nullOnDelete();
            $table->foreign('approver_user_id')
                  ->references('id')->on('users')
                  ->nullOnDelete();

            $table->unique(['approval_flow_id', 'step_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_flow_steps');
        Schema::dropIfExists('approval_flows');
    }
};
