<?php

namespace Tests\Feature;

use App\Jobs\ProcessUserImport;
use App\Models\ImportJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * UM-09 FASE 3 — Import background: job memproses file, menulis progress &
 * baris gagal ke ImportJob; baris valid tetap masuk (partial error).
 */
class UserImportJobTest extends TestCase
{
    use RefreshDatabase;

    private function makeFile(array $rows, string $name = 'imp.xlsx'): string
    {
        $export = new class($rows) implements FromArray, WithHeadings {
            public function __construct(private array $rows) {}
            public function headings(): array { return ['nama', 'email', 'nik']; }
            public function array(): array { return $this->rows; }
        };
        Excel::store($export, $name, 'local');
        return $name;
    }

    public function test_import_background_partial_error_terekam(): void
    {
        // Prasyarat: 1 user existing dgn NIK EMP-001 → picu duplikat.
        User::factory()->create(['email' => 'existing@demo.test', 'employee_id' => 'EMP-001']);

        $rows = [
            ['Valid Satu', 'valid1@demo.test', 'EMP-101'],   // ok
            ['Valid Dua',  'valid2@demo.test', 'EMP-102'],   // ok
            ['Email Rusak', 'bukan-email',      'EMP-103'],   // gagal: email invalid
            ['NIK Dobel',  'valid3@demo.test',  'EMP-001'],   // gagal: nik duplikat
            ['',           'valid4@demo.test',  'EMP-104'],   // gagal: nama kosong
        ];
        $path = $this->makeFile($rows);

        $job = ImportJob::create([
            'type' => 'user', 'file_path' => $path, 'status' => ImportJob::STATUS_QUEUED,
            'total_rows' => count($rows),
        ]);

        // Jalankan job secara langsung (sinkron di test).
        (new ProcessUserImport($job->id))->handle();

        $job->refresh();

        $this->assertSame(ImportJob::STATUS_DONE, $job->status, 'status harus done');
        $this->assertSame(2, $job->imported, '2 baris valid harus masuk');
        $this->assertSame(3, $job->skipped, '3 baris gagal harus dilewati');

        // Baris valid benar-benar ada di DB.
        $this->assertDatabaseHas('users', ['email' => 'valid1@demo.test']);
        $this->assertDatabaseHas('users', ['email' => 'valid2@demo.test']);
        // Baris gagal TIDAK masuk.
        $this->assertDatabaseMissing('users', ['email' => 'valid3@demo.test']);

        // Detail error terekam (baris/kolom/alasan).
        $this->assertIsArray($job->errors);
        $this->assertGreaterThanOrEqual(3, count($job->errors));
        $reasons = collect($job->errors)->pluck('reason')->implode(' | ');
        $this->assertStringContainsStringIgnoringCase('email', $reasons);
    }

    public function test_file_hilang_status_failed(): void
    {
        $job = ImportJob::create([
            'type' => 'user', 'file_path' => 'imports/tidak-ada.xlsx',
            'status' => ImportJob::STATUS_QUEUED,
        ]);

        (new ProcessUserImport($job->id))->handle();

        $job->refresh();
        $this->assertSame(ImportJob::STATUS_FAILED, $job->status);
        $this->assertStringContainsStringIgnoringCase('tidak ditemukan', $job->message);
    }
}
