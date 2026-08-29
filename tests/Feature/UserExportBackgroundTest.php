<?php

namespace Tests\Feature;

use App\Jobs\ProcessUserExport;
use App\Models\ExportJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * UM-10 — Export karyawan background: dispatch, job tulis file + scope,
 * status/unduh, retensi 24 jam, otorisasi.
 */
class UserExportBackgroundTest extends TestCase
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

    public function test_export_dispatch_background_dan_redirect(): void
    {
        Bus::fake();
        $admin = $this->admin();

        $res = $this->actingAs($admin, config('backpack.base.guard', 'backpack'))
            ->get(route('user.export.all'));

        $this->assertDatabaseCount('export_jobs', 1);
        $job = ExportJob::first();
        $this->assertSame('queued', $job->status);
        $res->assertRedirect(route('user.export.status', $job->id));
        Bus::assertDispatched(ProcessUserExport::class);
    }

    public function test_job_tulis_file_xlsx_dan_status_done(): void
    {
        $admin = $this->admin();
        $job = ExportJob::create([
            'user_id' => $admin->id, 'type' => 'user',
            'status' => ExportJob::STATUS_QUEUED, 'total' => 1,
        ]);

        (new ProcessUserExport($job->id))->handle();

        $job->refresh();
        $this->assertSame(ExportJob::STATUS_DONE, $job->status);
        $this->assertNotNull($job->file_path);
        $this->assertTrue(Storage::disk('local')->exists($job->file_path));
        $this->assertNotNull($job->expires_at);
        $this->assertTrue($job->expires_at->isFuture());

        Storage::disk('local')->delete($job->file_path);
    }

    public function test_download_belum_siap_404(): void
    {
        $admin = $this->admin();
        $job = ExportJob::create(['user_id' => $admin->id, 'type' => 'user', 'status' => 'processing']);
        $this->actingAs($admin, config('backpack.base.guard', 'backpack'))
            ->get(route('user.export.download', $job->id))
            ->assertNotFound();
    }

    public function test_download_expired_410(): void
    {
        $admin = $this->admin();
        Storage::disk('local')->put('exports/expired.xlsx', 'x');
        $job = ExportJob::create([
            'user_id' => $admin->id, 'type' => 'user', 'status' => 'done',
            'file_path' => 'exports/expired.xlsx', 'expires_at' => now()->subHour(),
        ]);
        $this->actingAs($admin, config('backpack.base.guard', 'backpack'))
            ->get(route('user.export.download', $job->id))
            ->assertStatus(410);
        Storage::disk('local')->delete('exports/expired.xlsx');
    }

    public function test_status_orang_lain_ditolak(): void
    {
        $admin = $this->admin();
        $lain = User::factory()->create();
        $job = ExportJob::create(['user_id' => $lain->id, 'type' => 'user', 'status' => 'done']);
        $this->actingAs($admin, config('backpack.base.guard', 'backpack'))
            ->getJson(route('user.export.status.json', $job->id))
            ->assertForbidden();
    }
}
