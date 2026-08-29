<?php

namespace Tests\Feature;

use App\Jobs\GenerateIdCardsJob;
use App\Models\PrintJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * UM-11 — Cetak ID aman skala: terpilih (scoped), ambang → background,
 * unduh PDF, otorisasi.
 */
class UserPrintIdCardTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $u = User::factory()->create();
        foreach (['user.view'] as $p) {
            foreach (['web', config('backpack.base.guard', 'backpack')] as $g) {
                Permission::firstOrCreate(['name' => $p, 'guard_name' => $g]);
                $u->givePermissionTo(Permission::where('name', $p)->where('guard_name', $g)->first());
            }
        }
        return $u;
    }

    private function adminViewAll(): User
    {
        $u = $this->admin();
        foreach (['web', config('backpack.base.guard', 'backpack')] as $g) {
            Permission::firstOrCreate(['name' => 'user.view_all', 'guard_name' => $g]);
            $u->givePermissionTo(Permission::where('name', 'user.view_all')->where('guard_name', $g)->first());
        }
        return $u;
    }

    public function test_print_selected_tanpa_ids_422(): void
    {
        $admin = $this->admin();
        $res = $this->actingAs($admin, config('backpack.base.guard', 'backpack'))
            ->get(route('user.print.selected'));
        $res->assertStatus(422);
    }

    public function test_print_selected_besar_dispatch_background(): void
    {
        Bus::fake();
        $admin = $this->adminViewAll();
        // Buat 201 user (> ambang 200).
        User::factory()->count(201)->create();
        $ids = User::query()->limit(201)->pluck('id')->implode(',');

        $res = $this->actingAs($admin, config('backpack.base.guard', 'backpack'))
            ->get(route('user.print.selected', ['ids' => $ids]));

        $this->assertDatabaseCount('print_jobs', 1);
        $job = PrintJob::first();
        $res->assertRedirect(route('user.print.status', $job->id));
        Bus::assertDispatched(GenerateIdCardsJob::class);
    }

    public function test_status_json_progress(): void
    {
        $admin = $this->admin();
        $job = PrintJob::create([
            'user_id' => $admin->id, 'type' => 'id_card', 'status' => 'processing',
            'total' => 500, 'processed' => 125,
        ]);
        $res = $this->actingAs($admin, config('backpack.base.guard', 'backpack'))
            ->getJson(route('user.print.status.json', $job->id));
        $res->assertOk()->assertJson([
            'status' => 'processing', 'total' => 500, 'processed' => 125,
            'progress' => 25, 'finished' => false, 'ready' => false,
        ]);
    }

    public function test_download_belum_siap_404(): void
    {
        $admin = $this->admin();
        $job = PrintJob::create([
            'user_id' => $admin->id, 'type' => 'id_card', 'status' => 'processing', 'total' => 10,
        ]);
        $res = $this->actingAs($admin, config('backpack.base.guard', 'backpack'))
            ->get(route('user.print.download', $job->id));
        $res->assertNotFound();
    }

    public function test_status_orang_lain_ditolak(): void
    {
        $admin = $this->admin();
        $lain = User::factory()->create();
        $job = PrintJob::create(['user_id' => $lain->id, 'type' => 'id_card', 'status' => 'done']);
        $res = $this->actingAs($admin, config('backpack.base.guard', 'backpack'))
            ->getJson(route('user.print.status.json', $job->id));
        $res->assertForbidden();
    }
}
