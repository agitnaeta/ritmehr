<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M12c — Friendly "Catat Transaksi" support.
 *
 * - accounts.is_cash        : flags which accounts are real money (Kas/Bank) so
 *                             the simple form can offer them as pay-from/into.
 * - journal_entries.kind    : which simple mode created it (expense/income/
 *                             transfer/general) so Edit reopens the right form.
 * - attachment_path/name    : proof of transaction (nota/kwitansi), private disk.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->boolean('is_cash')->default(false)->after('role');
        });

        Schema::table('journal_entries', function (Blueprint $table) {
            $table->string('kind')->default('general')->after('source');
            $table->string('attachment_path')->nullable()->after('reversed_entry_id');
            $table->string('attachment_name')->nullable()->after('attachment_path');
        });
    }

    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropColumn('is_cash');
        });
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->dropColumn(['kind', 'attachment_path', 'attachment_name']);
        });
    }
};
