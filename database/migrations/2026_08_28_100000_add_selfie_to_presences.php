<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M22-1 — Self-Attendance (Camera Location Mode) foundation.
 *
 * Additive columns on `presences`: selfie proof, record source (qr|camera),
 * GPS accuracy, and the approval workflow for out-of-radius camera check-ins.
 * All existing columns (lat/lng/outside/branch_id) are reused as-is.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('presences', function (Blueprint $table) {
            $table->string('selfie_path')->nullable()->after('lng');   // bukti foto (StorageManager M16)
            $table->string('source')->default('qr')->after('selfie_path'); // 'qr' | 'camera'
            $table->decimal('accuracy', 8, 2)->nullable()->after('source'); // akurasi GPS (meter)

            // Q3 — absen di luar radius (Camera Mode) tetap tercatat tapi butuh approval manajer.
            $table->string('approval_status')->default('approved')->after('accuracy'); // approved|pending|rejected
            $table->text('approval_note')->nullable()->after('approval_status');        // alasan/catatan manajer
            $table->unsignedBigInteger('approved_by')->nullable()->after('approval_note'); // manajer yg memutuskan
        });
    }

    public function down(): void
    {
        Schema::table('presences', function (Blueprint $table) {
            $table->dropColumn([
                'selfie_path',
                'source',
                'accuracy',
                'approval_status',
                'approval_note',
                'approved_by',
            ]);
        });
    }
};
