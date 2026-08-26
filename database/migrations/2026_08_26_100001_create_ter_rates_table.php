<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M19 — PPh 21 TER (Tarif Efektif Rata-rata) rate table.
 *
 * Effective monthly withholding rates per PP 58/2023 & PMK 168/2023, split into
 * three categories (A/B/C) derived from the employee's PTKP status. Versioned by
 * year like pph21_brackets so a historical recalculation uses period-correct
 * rates. Rates are DATA, never hardcoded in the service.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ter_rates', function (Blueprint $table) {
            $table->id();
            $table->integer('year');
            $table->enum('category', ['A', 'B', 'C']);
            $table->bigInteger('lower_bound');              // inclusive floor of the bracket
            $table->bigInteger('upper_bound')->nullable();  // null = top bracket (open-ended)
            $table->decimal('rate', 5, 2);                  // percent, e.g. 2.00
            $table->timestamps();

            $table->unique(['year', 'category', 'lower_bound']);
            $table->index(['year', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ter_rates');
    }
};
