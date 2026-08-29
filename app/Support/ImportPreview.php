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
     * @param  int       $limit        maksimal baris yang DITAMPILKAN di tabel pratinjau
     * @return array{headings:array,rows:array,validCount:int,errorCount:int,totalCount:int,shownCount:int}
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

        // Hitung valid/error dari SELURUH baris (bukan hanya yang ditampilkan),
        // supaya angka "baris terbaca" & "baris valid" akurat untuk file besar.
        foreach ($reader->data as $index => $row) {
            $ok = true;
            foreach ($required as $col) {
                if (blank($row[$col] ?? null)) { $ok = false; break; }
            }
            $ok ? $valid++ : $errors++;

            // Tabel pratinjau hanya menampilkan $limit baris pertama demi performa.
            if ($index < $limit) {
                $cells = [];
                foreach ($showColumns as $col) {
                    $cells[] = $row[$col] ?? null;
                }
                $rows[] = ['valid' => $ok, 'cells' => $cells];
            }
        }

        return [
            'headings'   => $showColumns,
            'rows'       => $rows,
            'validCount' => $valid,
            'errorCount' => $errors,
            'totalCount' => $valid + $errors,
            'shownCount' => count($rows),
        ];
    }
}
