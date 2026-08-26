<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M12 — Support manual journals & reversals.
 *
 * `reversed_entry_id` links a reversing entry back to the one it cancels, so an
 * auto-posted (locked) entry can be corrected without editing/deleting it —
 * keeping the audit trail intact.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->foreignId('reversed_entry_id')->nullable()->after('external_id')
                  ->constrained('journal_entries')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reversed_entry_id');
        });
    }
};
