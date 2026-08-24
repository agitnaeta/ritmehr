<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_tax_profiles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->unique();
            $table->string('npwp', 30)->nullable();
            $table->enum('tax_status', [
                'TK/0', 'TK/1', 'TK/2', 'TK/3',
                'K/0', 'K/1', 'K/2', 'K/3',
                'K/I/0', 'K/I/1', 'K/I/2', 'K/I/3',
            ])->default('TK/0');
            $table->enum('tax_method', ['gross', 'gross_up', 'nett'])->default('gross');
            $table->boolean('bpjs_kesehatan')->default(true);
            $table->boolean('bpjs_ketenagakerjaan')->default(true);
            $table->boolean('bpjs_tk_jht')->default(true);
            $table->boolean('bpjs_tk_jp')->default(true);
            $table->boolean('bpjs_tk_jkk')->default(true);
            $table->boolean('bpjs_tk_jkm')->default(true);
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::create('ptkp_rates', function (Blueprint $table) {
            $table->id();
            $table->integer('year');
            $table->string('status', 10);
            $table->bigInteger('amount');
            $table->timestamps();

            $table->unique(['year', 'status']);
        });

        Schema::create('pph21_brackets', function (Blueprint $table) {
            $table->id();
            $table->integer('year');
            $table->bigInteger('lower_bound');
            $table->bigInteger('upper_bound')->nullable();   // null = top bracket
            $table->decimal('rate', 5, 2);
            $table->timestamps();

            $table->unique(['year', 'lower_bound']);
        });

        Schema::create('bpjs_rates', function (Blueprint $table) {
            $table->id();
            $table->integer('year');
            $table->string('type', 30);                       // kesehatan | jht | jp | jkk | jkm
            $table->decimal('employer_rate', 5, 2);
            $table->decimal('employee_rate', 5, 2);
            $table->bigInteger('max_salary')->nullable();      // contribution ceiling
            $table->timestamps();

            $table->unique(['year', 'type']);
        });

        Schema::table('salary_recaps', function (Blueprint $table) {
            $table->bigInteger('pph21')->default(0);
            $table->bigInteger('bpjs_kes_employee')->default(0);
            $table->bigInteger('bpjs_kes_employer')->default(0);
            $table->bigInteger('bpjs_jht_employee')->default(0);
            $table->bigInteger('bpjs_jht_employer')->default(0);
            $table->bigInteger('bpjs_jp_employee')->default(0);
            $table->bigInteger('bpjs_jp_employer')->default(0);
            $table->bigInteger('bpjs_jkk')->default(0);
            $table->bigInteger('bpjs_jkm')->default(0);
            $table->bigInteger('thr')->default(0);
            $table->bigInteger('bonus')->default(0);
            $table->bigInteger('gross_income')->default(0);
            $table->bigInteger('net_income')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('salary_recaps', function (Blueprint $table) {
            $table->dropColumn([
                'pph21',
                'bpjs_kes_employee', 'bpjs_kes_employer',
                'bpjs_jht_employee', 'bpjs_jht_employer',
                'bpjs_jp_employee', 'bpjs_jp_employer',
                'bpjs_jkk', 'bpjs_jkm',
                'thr', 'bonus', 'gross_income', 'net_income',
            ]);
        });

        Schema::dropIfExists('bpjs_rates');
        Schema::dropIfExists('pph21_brackets');
        Schema::dropIfExists('ptkp_rates');
        Schema::dropIfExists('employee_tax_profiles');
    }
};
