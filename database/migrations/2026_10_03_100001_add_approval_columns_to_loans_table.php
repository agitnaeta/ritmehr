<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Every loan that exists today was booked the moment it was saved, and HR
     * direct entry keeps working that way — so the column defaults to
     * 'approved' and existing rows are backfilled by the default itself.
     * Employee requests will explicitly create 'pending' rows.
     */
    public function up(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->string('status', 20)->default('approved')->index();
            $table->text('reason')->nullable();
            $table->text('rejection_reason')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropColumn(['status', 'reason', 'rejection_reason']);
        });
    }
};
