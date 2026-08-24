<?php

use App\Models\CompanyProfile;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_profile_id')->nullable();
            $table->string('name', 100);
            $table->string('code', 20)->nullable()->unique();
            $table->text('address')->nullable();
            $table->string('phone', 20)->nullable();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->integer('radius_meters')->default(100);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('company_profile_id')
                  ->references('id')->on('company_profiles')
                  ->nullOnDelete();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('branch_id')->nullable()->after('department_id');
            $table->foreign('branch_id')->references('id')->on('branches')->nullOnDelete();
        });

        Schema::table('presences', function (Blueprint $table) {
            $table->unsignedBigInteger('branch_id')->nullable()->after('user_id');
            $table->foreign('branch_id')->references('id')->on('branches')->nullOnDelete();
        });

        $this->createDefaultBranch();
    }

    /**
     * Existing installations are single-site. Create one branch from the
     * current global geofence config and attach every user to it, so
     * behaviour is unchanged immediately after the upgrade.
     */
    private function createDefaultBranch(): void
    {
        $company = CompanyProfile::first();

        $branchId = DB::table('branches')->insertGetId([
            'company_profile_id' => $company?->id,
            'name'               => $company?->name ?: 'Kantor Pusat',
            'code'               => 'HO',
            'address'            => $company?->address,
            'phone'              => $company?->phone,
            'lat'                => (float) config('app.office_lat') ?: null,
            'lng'                => (float) config('app.office_lng') ?: null,
            'radius_meters'      => (int) (config('app.office_radius') ?: 100),
            'is_active'          => true,
            'created_at'         => now(),
            'updated_at'         => now(),
        ]);

        DB::table('users')->whereNull('branch_id')->update(['branch_id' => $branchId]);
    }

    public function down(): void
    {
        Schema::table('presences', function (Blueprint $table) {
            $table->dropForeign(['branch_id']);
            $table->dropColumn('branch_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['branch_id']);
            $table->dropColumn('branch_id');
        });

        Schema::dropIfExists('branches');
    }
};
