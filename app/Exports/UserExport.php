<?php

namespace App\Exports;

use App\Models\User;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * QW-02 / PERF-6 — Export karyawan yang aman & hemat memori.
 *
 * - FromQuery + WithChunkReading: proses per-1000 baris, memori rata (tidak
 *   memuat seluruh tabel ke RAM seperti `User::all()` sebelumnya).
 * - select kolom non-sensitif SAJA: password & remember_token TIDAK pernah
 *   ikut ter-serialize ke file Excel.
 */
class UserExport implements FromQuery, WithHeadings, WithMapping, WithChunkReading
{
    /**
     * @param User|null $viewer Pembatas visibilitas (scope visibleTo). Manager
     *        hanya mengekspor bawahannya; super_admin/hr_admin (user.view_all) semua.
     */
    public function __construct(private ?User $viewer = null)
    {
    }

    public function query()
    {
        return User::query()
            ->visibleTo($this->viewer)
            ->select([
                'id', 'employee_id', 'name', 'email', 'phone', 'address',
                'employment_status', 'join_date', 'created_at',
            ])
            ->orderBy('id');
    }

    public function headings(): array
    {
        return [
            'ID', 'NIP', 'Nama', 'Email', 'Telepon', 'Alamat',
            'Status Kerja', 'Tanggal Bergabung', 'Dibuat',
        ];
    }

    public function map($user): array
    {
        return [
            $user->id,
            $user->employee_id,
            $user->name,
            $user->email,
            $user->phone,
            $user->address,
            $user->employment_status,
            optional($user->join_date)->format('Y-m-d'),
            optional($user->created_at)->format('Y-m-d H:i'),
        ];
    }

    public function chunkSize(): int
    {
        return 1000; // memori rata, tidak spike pada data besar
    }
}
