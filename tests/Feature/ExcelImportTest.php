<?php

namespace Tests\Feature;

use App\Imports\SalaryImport;
use App\Imports\UserImport;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Salary;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

/**
 * IMP-01 / IMP-02 — Import karyawan & gaji dari Excel.
 *
 * Menguji jalur nyata: bikin file CSV → Excel::import → assert DB.
 */
class ExcelImportTest extends TestCase
{
    use RefreshDatabase;

    private function csv(string $name, string $content): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'imp') . '.csv';
        file_put_contents($path, $content);

        return new UploadedFile($path, $name, 'text/csv', null, true);
    }

    // ── IMP-01 UserImport ──────────────────────────────────

    public function test_user_import_creates_employees_and_resolves_org(): void
    {
        $file = $this->csv('karyawan.csv',
            "nama,email,tgl_bergabung,departemen,cabang,jabatan,password\n" .
            "Andi Wijaya,andi@imp.test,2024-01-15,Teknologi,Kantor Pusat,Staff,rahasia\n" .
            "Bunga Sari,bunga@imp.test,2023-05-01,HRD,Kantor Pusat,Manager,\n"
        );

        Excel::import(new UserImport, $file);

        $this->assertDatabaseHas('users', ['email' => 'andi@imp.test', 'name' => 'Andi Wijaya']);
        $this->assertDatabaseHas('users', ['email' => 'bunga@imp.test']);
        // Departemen & cabang dibuat dari nama.
        $this->assertDatabaseHas('departments', ['name' => 'Teknologi']);
        $this->assertDatabaseHas('branches', ['name' => 'Kantor Pusat']);

        $andi = User::where('email', 'andi@imp.test')->first();
        $this->assertNotNull($andi->department_id);
        $this->assertNotNull($andi->branch_id);
    }

    public function test_user_import_is_idempotent_by_email(): void
    {
        $csv = "nama,email\nAndi Wijaya,andi@imp.test\n";

        Excel::import(new UserImport, $this->csv('a.csv', $csv));
        Excel::import(new UserImport, $this->csv('a.csv', $csv));

        $this->assertSame(1, User::where('email', 'andi@imp.test')->count());
    }

    public function test_user_import_rejects_row_without_email(): void
    {
        $file = $this->csv('bad.csv', "nama,email\nTanpa Email,\nValid User,valid@imp.test\n");

        // SkipsOnFailure: baris invalid dilewati, baris valid tetap masuk.
        Excel::import(new UserImport, $file);

        $this->assertDatabaseHas('users', ['email' => 'valid@imp.test']);
        $this->assertSame(0, User::whereNull('email')->orWhere('email', '')->count());
    }

    // ── IMP-02 SalaryImport ────────────────────────────────

    public function test_salary_import_upserts_and_parses_currency(): void
    {
        User::create(['name' => 'Andi', 'email' => 'andi@imp.test', 'password' => bcrypt('x')]);

        $file = $this->csv('gaji.csv',
            "email,gaji_pokok,lembur_1x,denda_per_menit,potongan_absen\n" .
            "andi@imp.test,7.500.000,75.000,1.000,0\n"
        );

        Excel::import(new SalaryImport, $file);

        $andi = User::where('email', 'andi@imp.test')->first();
        $salary = Salary::where('user_id', $andi->id)->first();

        $this->assertNotNull($salary);
        $this->assertSame(7_500_000, (int) $salary->basic_salary);
        // amount di-recalc observer (tanpa tunjangan → sama dgn basic).
        $this->assertSame(7_500_000, (int) $salary->amount);
    }

    public function test_salary_import_collects_unmatched_emails(): void
    {
        $import = new SalaryImport;
        $file = $this->csv('gaji.csv',
            "email,gaji_pokok\ntidak_ada@imp.test,5.000.000\n"
        );

        Excel::import($import, $file);

        $this->assertSame(0, Salary::count());
        $this->assertContains('tidak_ada@imp.test', $import->unmatched);
    }
}
