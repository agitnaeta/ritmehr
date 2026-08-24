<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('code', 20)->nullable()->unique();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->unsignedBigInteger('head_user_id')->nullable();
            $table->timestamps();

            $table->foreign('parent_id')->references('id')->on('departments')->nullOnDelete();
            $table->foreign('head_user_id')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('positions', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->integer('level')->default(0);
            $table->unsignedBigInteger('department_id')->nullable();
            $table->timestamps();

            $table->foreign('department_id')->references('id')->on('departments')->nullOnDelete();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('department_id')->nullable()->after('manager_id');
            $table->unsignedBigInteger('position_id')->nullable()->after('department_id');
            $table->string('employee_id', 20)->nullable()->unique()->after('position_id');
            $table->date('join_date')->nullable()->after('employee_id');
            $table->enum('employment_status', ['active', 'probation', 'resigned', 'terminated'])
                  ->default('active')->after('join_date');
            $table->string('phone', 20)->nullable()->after('employment_status');
            $table->text('address')->nullable()->after('phone');

            $table->foreign('department_id')->references('id')->on('departments')->nullOnDelete();
            $table->foreign('position_id')->references('id')->on('positions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['department_id']);
            $table->dropForeign(['position_id']);
            $table->dropColumn([
                'department_id', 'position_id', 'employee_id',
                'join_date', 'employment_status', 'phone', 'address',
            ]);
        });

        Schema::dropIfExists('positions');
        Schema::dropIfExists('departments');
    }
};
