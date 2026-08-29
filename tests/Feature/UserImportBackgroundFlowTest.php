<?php

namespace Tests\Feature;

use App\Jobs\ProcessUserImport;
use App\Models\ImportJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * UM-09 FASE 4/5 — importStore SELALU dispatch background; halaman status,
 * status JSON (polling), dan unduh CSV baris gagal.
 */
class UserImportBackgroundFlowTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $u = User::factory()->create();
        foreach (['user.view', 'user.create'] as $p) {
            foreach (['web', config('backpack.base.guard', 'backpack')] as $g) {
                Permission::firstOrCreate(['name' => $p, 'guard_name' => $g]);
            }
            $u->givePermissionTo(Permission::where('name', $p)->where('guard_name', 'web')->first());
            $u->givePermissionTo(Permission::where('name', $p)->where('guard_name', config('backpack.base.guard', 'backpack'))->first());
        }
        return $u;
    }

    private function makeUpload(): UploadedFile
    {
        $export = new class implements FromArray, WithHeadings {
            public function headings(): array { return ['nama', 'email', 'nik']; }
            public function array(): array {
                return [['A', 'a@demo.test', 'X-1'], ['B', 'b@demo.test', 'X-2']];
            }
        };
        Excel::store($export, 'up.xlsx', 'local');
        $abs = Storage::disk('local')->path('up.xlsx');
        return new UploadedFile($abs, 'karyawan.xlsx', null, null, true);
    }

    public function test_import_store_dispatch_background_dan_redirect_status(): void
    {
        Bus::fake();
        $admin = $this->admin();

        $res = $this->actingAs($admin, config('backpack.base.guard', 'backpack'))
            ->post(route('user.import.store'), ['file' => $this->makeUpload()]);

        // Dibuat 1 ImportJob + redirect ke halaman status-nya.
        $this->assertDatabaseCount('import_jobs', 1);
        $job = ImportJob::first();
        $this->assertSame('queued', $job->status);
        $this->assertSame($admin->id, $job->user_id);
        $res->assertRedirect(route('user.import.status', $job->id));

        // Job background di-dispatch (tidak diproses sinkron di request).
        Bus::assertDispatched(ProcessUserImport::class);
    }

    public function test_status_json_mengembalikan_progress(): void
    {
        $admin = $this->admin();
        $job = ImportJob::create([
            'user_id' => $admin->id, 'type' => 'user', 'status' => 'processing',
            'total_rows' => 100, 'processed' => 40, 'imported' => 38, 'skipped' => 2,
            'errors' => [['row' => 5, 'column' => 'email', 'value' => 'x', 'reason' => 'invalid']],
        ]);

        $res = $this->actingAs($admin, config('backpack.base.guard', 'backpack'))
            ->getJson(route('user.import.status.json', $job->id));

        $res->assertOk()
            ->assertJson([
                'status' => 'processing', 'total' => 100, 'processed' => 40,
                'imported' => 38, 'skipped' => 2, 'progress' => 40,
                'errorTotal' => 1, 'finished' => false,
            ]);
    }

    public function test_unduh_csv_baris_gagal(): void
    {
        $admin = $this->admin();
        $job = ImportJob::create([
            'user_id' => $admin->id, 'type' => 'user', 'status' => 'done',
            'total_rows' => 3, 'imported' => 2, 'skipped' => 1,
            'errors' => [['row' => 3, 'column' => 'email', 'value' => 'bad', 'reason' => 'Format email tidak valid.']],
        ]);

        $res = $this->actingAs($admin, config('backpack.base.guard', 'backpack'))
            ->get(route('user.import.errors.csv', $job->id));

        $res->assertOk();
        $res->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $content = $res->streamedContent();
        $this->assertStringContainsString('baris,kolom,nilai,alasan', $content);
        $this->assertStringContainsString('Format email tidak valid.', $content);
    }

    public function test_status_milik_orang_lain_ditolak(): void
    {
        $admin = $this->admin();
        $lain = User::factory()->create();
        $job = ImportJob::create(['user_id' => $lain->id, 'type' => 'user', 'status' => 'done']);

        $res = $this->actingAs($admin, config('backpack.base.guard', 'backpack'))
            ->getJson(route('user.import.status.json', $job->id));

        $res->assertForbidden();
    }
}
