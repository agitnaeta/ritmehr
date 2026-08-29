<?php

namespace Tests\Feature;

use App\Exports\UserExport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * B2 (review-version-4) — Guard keamanan export/print karyawan.
 *
 * Route custom user (export, print, print-all) tidak melewati setup() CRUD,
 * sehingga dulu TANPA guard permission maupun scope visibleTo. Test ini mengunci:
 *   - user tanpa user.view ditolak 403 di export & print-all
 *   - export ter-scope visibleTo: manager hanya dapat bawahannya
 *   - print ID milik luar-wewenang → 404
 */
class UserExportPrintSecurityTest extends TestCase
{
    use RefreshDatabase;

    private function guard(): string
    {
        return config('backpack.base.guard', 'backpack');
    }

    /**
     * Grant a permission the way it must work at runtime.
     *
     * `backpack_user()->can()` (dipakai controller) resolve pada guard BACKPACK,
     * sedangkan `$user->can()` di dalam scope visibleTo resolve pada guard MODEL
     * (web). User model berbagi provider, jadi kita beri di KEDUA guard supaya
     * kedua jalur otorisasi terpenuhi — mirror kondisi produksi (role web + admin).
     */
    private function grant(User $user, string $name): void
    {
        foreach (['web', config('backpack.base.guard', 'backpack')] as $guard) {
            $user->givePermissionTo(
                Permission::firstOrCreate(['name' => $name, 'guard_name' => $guard])
            );
        }
    }

    private function user(string $name, array $attrs = []): User
    {
        return User::create(array_merge([
            'name'     => $name,
            'email'    => str($name)->slug() . uniqid() . '@example.test',
            'password' => bcrypt('secret'),
        ], $attrs));
    }

    public function test_export_ditolak_tanpa_permission(): void
    {
        $user = $this->user('Tanpa Izin'); // role-less, tanpa user.view
        // CheckIfAdmin memperlakukan akun role-less sebagai admin, jadi request
        // sampai ke guard permission di controller — yang harus menolak 403.
        $this->actingAs($user, $this->guard())
            ->get(route('user.export.all'))
            ->assertForbidden();
    }

    public function test_print_all_ditolak_tanpa_permission(): void
    {
        $user = $this->user('Tanpa Izin2');
        $this->actingAs($user, $this->guard())
            ->get(route('user.print.all'))
            ->assertForbidden();
    }

    public function test_export_query_discope_visibleTo_manager(): void
    {
        // Manager hanya punya user.view (bukan user.view_all).
        $manager = $this->user('Pak Manager');
        $this->grant($manager, 'user.view');

        $bawahan  = $this->user('Bawahan Langsung', ['manager_id' => $manager->id]);
        $orangLain = $this->user('Orang Lain Tim'); // manager_id null → bukan bawahan

        // UserExport dengan viewer=manager harus hanya memuat manager + bawahannya.
        $ids = (new UserExport($manager))->query()->pluck('id')->all();

        $this->assertContains($bawahan->id, $ids, 'bawahan harus ikut ter-ekspor');
        $this->assertContains($manager->id, $ids, 'diri sendiri harus ikut');
        $this->assertNotContains($orangLain->id, $ids, 'orang di luar tim TIDAK boleh bocor');
    }

    public function test_export_viewer_null_dan_view_all_dapat_semua(): void
    {
        $admin = $this->user('HR Admin');
        $this->grant($admin, 'user.view');
        $this->grant($admin, 'user.view_all');

        $a = $this->user('Karyawan A');
        $b = $this->user('Karyawan B', ['manager_id' => $a->id]);

        $ids = (new UserExport($admin))->query()->pluck('id')->all();
        $this->assertContains($a->id, $ids);
        $this->assertContains($b->id, $ids);
    }

    public function test_print_id_luar_wewenang_404(): void
    {
        $manager = $this->user('Manager Cetak');
        $this->grant($manager, 'user.view');

        $orangLain = $this->user('Bukan Bawahan'); // di luar tim manager

        // backpack_user() memakai guard backpack; set web juga agar helper resolve.
        $this->actingAs($manager, 'web');
        $this->actingAs($manager, $this->guard())
            ->get(route('user.print', ['id' => $orangLain->id]))
            ->assertNotFound();
    }
}
