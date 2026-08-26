<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M12 — Internal accounting ledger (double-entry).
 *
 * Replaces the external Firefly III integration with a self-managed general
 * ledger: a chart of accounts plus balanced journal entries. Every posting has
 * at least two lines whose debits equal their credits.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Chart of accounts.
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();          // 1000, 5000, ...
            $table->string('name');
            $table->enum('type', ['asset', 'liability', 'equity', 'income', 'expense'])
                  ->default('asset');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('type');
        });

        // Journal entries (transactions).
        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->string('description');
            // Idempotency key mirrored from the source record
            // (e.g. ABSEN-GAJIAN-12). Unique so a re-post updates, not duplicates.
            $table->string('reference')->nullable()->unique();
            $table->string('source')->default('manual'); // salary, loan, loan_payment, manual
            $table->unsignedBigInteger('external_id')->nullable(); // originating record id
            $table->timestamps();

            $table->index(['source', 'external_id']);
            $table->index('date');
        });

        // Journal lines (the debits & credits).
        Schema::create('journal_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_entry_id')->constrained('journal_entries')->cascadeOnDelete();
            $table->foreignId('account_id')->constrained('accounts')->cascadeOnDelete();
            $table->decimal('debit', 18, 2)->default(0);
            $table->decimal('credit', 18, 2)->default(0);
            $table->timestamps();

            $table->index('account_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_lines');
        Schema::dropIfExists('journal_entries');
        Schema::dropIfExists('accounts');
    }
};
