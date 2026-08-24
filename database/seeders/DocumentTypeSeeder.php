<?php

namespace Database\Seeders;

use App\Models\DocumentType;
use Illuminate\Database\Seeder;

/**
 * Standard Indonesian HR document types. Idempotent — matched on `code`.
 */
class DocumentTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['code' => 'ktp',      'name' => 'KTP',            'has_expiry' => false, 'is_required' => true],
            ['code' => 'npwp',     'name' => 'NPWP',           'has_expiry' => false, 'is_required' => false],
            ['code' => 'kontrak',  'name' => 'Kontrak Kerja',  'has_expiry' => true,  'is_required' => true],
            ['code' => 'ijazah',   'name' => 'Ijazah',         'has_expiry' => false, 'is_required' => false],
            ['code' => 'bpjs_kes', 'name' => 'Kartu BPJS Kesehatan', 'has_expiry' => false, 'is_required' => false],
            ['code' => 'bpjs_tk',  'name' => 'Kartu BPJS Ketenagakerjaan', 'has_expiry' => false, 'is_required' => false],
            ['code' => 'kk',       'name' => 'Kartu Keluarga', 'has_expiry' => false, 'is_required' => false],
            ['code' => 'sp',       'name' => 'Surat Peringatan', 'has_expiry' => true, 'is_required' => false],
        ];

        foreach ($types as $type) {
            DocumentType::updateOrCreate(
                ['code' => $type['code']],
                $type + ['max_file_size_mb' => 5, 'allowed_extensions' => 'pdf,jpg,jpeg,png']
            );
        }

        $this->command?->info('Seeded ' . count($types) . ' document types.');
    }
}
