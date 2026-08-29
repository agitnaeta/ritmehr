<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * UM-05 — Default locale karyawan 'id' (Indonesia).
 */
class UserLocaleDefaultTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_baru_tanpa_locale_default_id(): void
    {
        $user = User::create([
            'name'     => 'Tanpa Locale',
            'email'    => 'nolocale@demo.test',
            'password' => 'password',
        ]);

        $this->assertSame('id', $user->fresh()->locale);
    }

    public function test_tidak_ada_user_locale_null_setelah_migrate(): void
    {
        // Buat beberapa user via factory (yang mungkin tak set locale eksplisit)
        User::factory()->count(3)->create();

        $this->assertSame(0, User::whereNull('locale')->count());
    }

    public function test_locale_eksplisit_tidak_ditimpa_default(): void
    {
        $user = User::create([
            'name'     => 'English User',
            'email'    => 'en@demo.test',
            'password' => 'password',
            'locale'   => 'en',
        ]);

        $this->assertSame('en', $user->fresh()->locale);
    }

    public function test_import_set_locale_id_saat_kolom_kosong(): void
    {
        $import = new \App\Imports\UserImport();
        $import->model([
            'email' => 'import@demo.test',
            'nama'  => 'Import Tanpa Bahasa',
        ]);

        $this->assertSame('id', User::where('email', 'import@demo.test')->value('locale'));
    }

    public function test_import_hormati_kolom_bahasa_eksplisit(): void
    {
        $import = new \App\Imports\UserImport();
        $import->model([
            'email'  => 'importen@demo.test',
            'nama'   => 'Import English',
            'bahasa' => 'en',
        ]);

        $this->assertSame('en', User::where('email', 'importen@demo.test')->value('locale'));
    }
}
