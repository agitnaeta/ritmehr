<?php

namespace Tests\Feature;

use App\Support\ImportPreview;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

/**
 * Pratinjau import harus menghitung SELURUH baris (validCount/totalCount),
 * meski tabel pratinjau hanya menampilkan sebagian (limit) demi performa.
 * Regresi bug: file 1000 baris tampil "100 baris".
 */
class ImportPreviewCountTest extends TestCase
{
    private function makeFile(int $rows): string
    {
        $export = new class($rows) implements FromArray, WithHeadings {
            public function __construct(private int $rows) {}
            public function headings(): array { return ['nama', 'email', 'nik']; }
            public function array(): array {
                $data = [];
                for ($i = 1; $i <= $this->rows; $i++) {
                    $data[] = ["User $i", "user$i@contoh.test", "EMP-" . str_pad((string) $i, 5, '0', STR_PAD_LEFT)];
                }
                return $data;
            }
        };

        Excel::store($export, 'preview-count-test.xlsx', 'local');
        return \Illuminate\Support\Facades\Storage::disk('local')->path('preview-count-test.xlsx');
    }

    public function test_count_akurat_untuk_file_lebih_dari_limit(): void
    {
        $path = $this->makeFile(1000);

        $preview = ImportPreview::build($path, ['email', 'nama'], ['nama', 'email', 'nik']);

        $this->assertSame(1000, $preview['totalCount'], 'totalCount harus 1000');
        $this->assertSame(1000, $preview['validCount'], 'validCount harus 1000');
        $this->assertSame(0, $preview['errorCount']);
        $this->assertSame(100, $preview['shownCount'], 'tabel pratinjau dibatasi 100 baris');
        $this->assertCount(100, $preview['rows']);

        @unlink($path);
    }

    public function test_count_penuh_untuk_file_kecil(): void
    {
        $path = $this->makeFile(5);

        $preview = ImportPreview::build($path, ['email', 'nama'], ['nama', 'email', 'nik']);

        $this->assertSame(5, $preview['totalCount']);
        $this->assertSame(5, $preview['validCount']);
        $this->assertSame(5, $preview['shownCount']);

        @unlink($path);
    }
}
