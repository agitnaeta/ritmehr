<?php

namespace Database\Seeders;

use App\Models\LeaveType;
use Illuminate\Database\Seeder;

/**
 * Default Indonesian leave types. Idempotent — matched on `code`.
 */
class LeaveTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            [
                'code'                 => 'annual',
                'name'                 => 'Cuti Tahunan',
                'is_paid'              => true,
                'default_quota'        => 12,          // UU Ketenagakerjaan minimum
                'max_consecutive_days' => null,
                'requires_attachment'  => false,
                'color'                => '#3498db',
            ],
            [
                'code'                 => 'sick',
                'name'                 => 'Sakit',
                'is_paid'              => true,
                'default_quota'        => null,        // not quota-tracked
                'max_consecutive_days' => null,
                'requires_attachment'  => true,        // surat dokter
                'color'                => '#e74c3c',
            ],
            [
                'code'                 => 'permission',
                'name'                 => 'Izin',
                'is_paid'              => true,
                'default_quota'        => 3,
                'max_consecutive_days' => 2,
                'requires_attachment'  => false,
                'color'                => '#f39c12',
            ],
            [
                'code'                 => 'maternity',
                'name'                 => 'Cuti Melahirkan',
                'is_paid'              => true,
                'default_quota'        => 90,          // 3 months
                'max_consecutive_days' => 90,
                'requires_attachment'  => true,
                'color'                => '#9b59b6',
            ],
            [
                'code'                 => 'unpaid',
                'name'                 => 'Cuti Tanpa Gaji',
                'is_paid'              => false,
                'default_quota'        => null,
                'max_consecutive_days' => null,
                'requires_attachment'  => false,
                'color'                => '#7f8c8d',
            ],
        ];

        foreach ($types as $type) {
            LeaveType::updateOrCreate(
                ['code' => $type['code']],
                $type + ['is_active' => true]
            );
        }

        $this->command?->info('Seeded ' . count($types) . ' leave types.');
    }
}
