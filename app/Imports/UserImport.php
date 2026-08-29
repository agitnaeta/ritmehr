<?php

namespace App\Imports;

use App\Models\Branch;
use App\Models\Department;
use App\Models\Position;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

/**
 * IMP-01 — Import karyawan dari Excel.
 *
 * Header (WithHeadingRow, snake_case): nama, email, tgl_bergabung,
 * departemen, cabang, jabatan, password, status.
 *
 * Idempoten by email (updateOrCreate). Departemen/Cabang/Jabatan dicari
 * atau dibuat dari nama sehingga data langsung ter-merge ke DB tanpa tabel
 * perantara. Baris tanpa email/nama dilewati (SkipsOnFailure) dan dikumpulkan
 * di $this->failures() untuk dilaporkan ke user — baris valid tetap masuk.
 */
class UserImport implements ToModel, WithHeadingRow, WithValidation, SkipsEmptyRows, SkipsOnFailure
{
    use SkipsFailures;

    /** @var int Jumlah baris yang benar-benar tersimpan. */
    public int $imported = 0;

    public function model(array $row)
    {
        $user = User::updateOrCreate(
            ['email' => trim($row['email'])],
            [
                'name'              => trim($row['nama']),
                'join_date'         => $this->date($row['tgl_bergabung'] ?? null),
                'department_id'     => $this->resolveId(Department::class, $row['departemen'] ?? null),
                'branch_id'         => $this->resolveId(Branch::class, $row['cabang'] ?? null),
                'position_id'       => $this->resolveId(Position::class, $row['jabatan'] ?? null),
                'password'          => Hash::make((string) ($row['password'] ?? 'password')),
                'employment_status' => $this->status($row['status'] ?? null),
                'locale'            => trim((string) ($row['bahasa'] ?? $row['locale'] ?? '')) ?: 'id',
            ]
        );

        $this->imported++;

        return $user;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'nama'  => ['required', 'string'],
        ];
    }

    public function customValidationMessages(): array
    {
        return [
            'email.required' => 'Kolom email wajib diisi.',
            'email.email'    => 'Format email tidak valid.',
            'nama.required'  => 'Kolom nama wajib diisi.',
        ];
    }

    /** Cari-atau-buat entity by name → id. Null-safe. */
    private function resolveId(string $model, ?string $name): ?int
    {
        $name = trim((string) $name);
        if ($name === '') {
            return null;
        }

        return $model::firstOrCreate(['name' => $name])->id;
    }

    /** Normalisasi status ke salah satu konstanta User; default active. */
    private function status(?string $raw): string
    {
        $raw = strtolower(trim((string) $raw));
        $map = [
            'active' => User::STATUS_ACTIVE, 'aktif' => User::STATUS_ACTIVE,
            'probation' => User::STATUS_PROBATION, 'percobaan' => User::STATUS_PROBATION,
            'resigned' => User::STATUS_RESIGNED, 'resign' => User::STATUS_RESIGNED,
            'terminated' => User::STATUS_TERMINATED,
        ];

        return $map[$raw] ?? User::STATUS_ACTIVE;
    }

    /** Terima "YYYY-MM-DD", serial Excel, atau kosong. */
    private function date($value): ?string
    {
        if (blank($value)) {
            return null;
        }

        if (is_numeric($value)) {
            return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float) $value)
                ->format('Y-m-d');
        }

        try {
            return \Illuminate\Support\Carbon::parse($value)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }
}
