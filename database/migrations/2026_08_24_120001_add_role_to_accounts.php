<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M12 refinement — give each account a functional role so posting rules can be
 * resolved from the chart of accounts alone. This replaces the separate `accs`
 * mapping table: everything is managed from /admin/account now.
 *
 * Roles: cash, salary_expense, loan_receivable (nullable = plain account).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->string('role')->nullable()->after('type');
            $table->index('role');
        });
    }

    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropIndex(['role']);
            $table->dropColumn('role');
        });
    }
};
