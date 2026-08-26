<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M15 — Platform Configuration.
 *
 * Central key/value store so a super admin can manage third-party credentials
 * and system config from the UI instead of editing .env + config:cache. Secret
 * values are stored encrypted (see SettingService).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->longText('value')->nullable();
            $table->string('group')->default('umum');   // umum, lokasi, notifikasi, akuntansi, storage, lokalisasi
            $table->string('type')->default('string');   // string, bool, int, float, password, select
            $table->boolean('is_encrypted')->default(false);
            $table->timestamps();

            $table->index('group');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
