<?php

namespace Tests\Feature;

use App\Imports\UserImport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Concerns\ToModel;
use Tests\TestCase;

/**
 * UM-04 — Import mengisi employee_id (NIK), opsional & unik.
 */
class UserImportNikTest extends TestCase
{
    use RefreshDatabase;

    public function test_import_baris_dengan_nik_tersimpan(): void
    {
        $import = new UserImport();
        $import->model([
            'email' => 'nikuser@demo.test',
            'nama'  => 'NIK User',
            'nik'   => 'EMP-999',
        ]);

        $this->assertSame('EMP-999', User::where('email', 'nikuser@demo.test')->value('employee_id'));
    }

    public function test_import_tanpa_nik_employee_id_null(): void
    {
        $import = new UserImport();
        $import->model([
            'email' => 'nonik@demo.test',
            'nama'  => 'Tanpa NIK',
        ]);

        $user = User::where('email', 'nonik@demo.test')->first();
        $this->assertNotNull($user);
        $this->assertNull($user->employee_id);
    }

    public function test_import_nik_kosong_string_jadi_null(): void
    {
        $import = new UserImport();
        $import->model([
            'email' => 'emptynik@demo.test',
            'nama'  => 'NIK Kosong',
            'nik'   => '   ',
        ]);

        $this->assertNull(User::where('email', 'emptynik@demo.test')->value('employee_id'));
    }

    public function test_rules_nik_nullable_dan_unique(): void
    {
        $rules = (new UserImport())->rules();

        $this->assertArrayHasKey('nik', $rules);
        $this->assertContains('nullable', $rules['nik']);
        $this->assertContains('unique:users,employee_id', $rules['nik']);
    }

    public function test_template_export_punya_kolom_nik(): void
    {
        $headings = (new \App\Exports\UserTemplateExport())->headings();

        $this->assertContains('nik', $headings);
        // Posisi nik setelah email
        $this->assertSame('email', $headings[array_search('nik', $headings) - 1]);
    }

    public function test_import_via_excel_file_end_to_end(): void
    {
        // Buat file xlsx via template lalu import — verifikasi jalur Excel::import penuh.
        $rows = new class implements \Maatwebsite\Excel\Concerns\FromArray, \Maatwebsite\Excel\Concerns\WithHeadings {
            public function headings(): array { return ['nama', 'email', 'nik']; }
            public function array(): array {
                return [['E2E User', 'e2e@demo.test', 'EMP-777']];
            }
        };

        // Excel::store menulis ke disk 'local'; ambil path absolut dari disk yang sama.
        Excel::store($rows, 'test-import-nik.xlsx', 'local');
        $absPath = \Illuminate\Support\Facades\Storage::disk('local')->path('test-import-nik.xlsx');

        $import = new UserImport();
        Excel::import($import, $absPath);

        $this->assertSame('EMP-777', User::where('email', 'e2e@demo.test')->value('employee_id'));

        @unlink($absPath);
    }
}
