<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * M20 — Salary breakdown: basic salary + named allowances.
 *
 * The single `salaries.amount` becomes a breakdown of basic_salary + a set of
 * allowances (free-label, defined once globally, valued per employee). `amount`
 * stays as the maintained total so tax/BPJS keep reading it unchanged.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Basic salary on the salary config. Backfill = whole amount is basic
        //    (existing employees start with all salary as basic, no allowances)
        //    so the total is unchanged on day one.
        Schema::table('salaries', function (Blueprint $table) {
            $table->bigInteger('basic_salary')->default(0)->after('user_id');
        });
        DB::table('salaries')->update(['basic_salary' => DB::raw('amount')]);

        // 2. Global master list of allowance types (free label, reusable).
        Schema::create('salary_allowance_types', function (Blueprint $table) {
            $table->id();
            $table->string('label');
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 3. Per-employee allowance value. Not filled = no row = 0.
        Schema::create('employee_salary_allowances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('salary_allowance_type_id')->constrained()->cascadeOnDelete();
            $table->bigInteger('amount')->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'salary_allowance_type_id'], 'emp_allowance_unique');
        });

        // 4. Frozen breakdown snapshot on each recap (label as it was that month).
        Schema::create('salary_recap_allowances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('salary_recap_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->bigInteger('amount')->default(0);
            $table->timestamps();
        });

        // 5. Snapshot basic salary on the recap too (for the payslip breakdown).
        Schema::table('salary_recaps', function (Blueprint $table) {
            $table->bigInteger('basic_salary')->default(0)->after('salary_amount');
        });
    }

    public function down(): void
    {
        Schema::table('salary_recaps', function (Blueprint $table) {
            $table->dropColumn('basic_salary');
        });
        Schema::dropIfExists('salary_recap_allowances');
        Schema::dropIfExists('employee_salary_allowances');
        Schema::dropIfExists('salary_allowance_types');
        Schema::table('salaries', function (Blueprint $table) {
            $table->dropColumn('basic_salary');
        });
    }
};
