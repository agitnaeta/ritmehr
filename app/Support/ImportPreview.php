<?php

namespace App\Support;

use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\HeadingRowImport;
use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * IMP-05 — Bangun data pratinjau import tanpa menyentuh DB.
 *
 * Membaca file Excel/CSV, memvalidasi kolom wajib per baris, dan mengembalikan
 * struktur siap-render untuk resources/views/admin/import/_layout.blade.php.
 */
class ImportPreview
{
    /**
     * @param  string    $path         path absolut file
     * @param  string[]  $required     kolom wajib (heading snake_case)
     * @param  string[]  $showColumns  kolom yang ditampilkan di tabel
     * @return array{headings:array,rows:array,validCount:int,errorCount:int}
     */
    public static function build(string $path, array $required, array $showColumns, int $limit = 100): array
    {
        $reader = new class implements ToArray, WithHeadingRow {
            public array $data = [];
            public function array(array $array): void { $this->data = $array; }
        };
        Excel::import($reader, $path);

        $rows = [];
        $valid = 0;
        $errors = 0;

        foreach (array_slice($reader->data, 0, $limit) as $row) {
            $ok = true;
            foreach ($required as $col) {
                if (blank($row[$col] ?? null)) { $ok = false; break; }
            }
            $ok ? $valid++ : $errors++;

            $cells = [];
            foreach ($showColumns as $col) {
                $cells[] = $row[$col] ?? null;
            }
            $rows[] = ['valid' => $ok, 'cells' => $cells];
        }

        return [
            'headings'   => $showColumns,
            'rows'       => $rows,
            'validCount' => $valid,
            'errorCount' => $errors,
        ];
    }
}
