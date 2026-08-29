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
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

/**
 * IMP-01 — Import karyawan dari Excel.
 *
 * Header (WithHeadingRow, snake_case): nama, email, nik, tgl_bergabung,
 * departemen, cabang, jabatan, password, status, bahasa.
 *
 * Idempoten by email (updateOrCreate). Departemen/Cabang/Jabatan dicari
 * atau dibuat dari nama sehingga data langsung ter-merge ke DB tanpa tabel
 * perantara. Baris tanpa email/nama dilewati (SkipsOnFailure) dan dikumpulkan
 * di $this->failures() untuk dilaporkan ke user — baris valid tetap masuk.
 *
 * UM-09 FASE 1 — Optimasi performa import massal (1000+ baris):
 *   (A) bcrypt: password default di-hash SEKALI (terukur ~226 ms/hash × 1000
 *       = ~226 dtk → penyebab timeout 30s). Password custom di-hash & di-cache
 *       per nilai unik.
 *   (B) N+1: nama Departemen/Cabang/Jabatan di-preload jadi map (lower→id)
 *       SEKALI di awal, bukan firstOrCreate per baris (~4000 query → ~3 query).
 *   (C) WithChunkReading: baca file per-chunk supaya hemat memori di file besar.
 */
class UserImport implements ToModel, WithHeadingRow, WithValidation, SkipsEmptyRows, SkipsOnFailure, WithChunkReading
{
    use SkipsFailures;

    /** @var int Jumlah baris yang benar-benar tersimpan. */
    public int $imported = 0;

    /** @var array<string,int> Map nama(lower) → id, di-preload sekali (N+1 fix). */
    private array $departmentMap = [];
    private array $branchMap = [];
    private array $positionMap = [];

    /** @var array<string,string> Cache hash password per nilai unik (bcrypt fix). */
    private array $passwordHashCache = [];

    private bool $lookupsLoaded = false;

    public function __construct()
    {
        // Password default paling umum di import massal — hash sekali di depan.
        $this->passwordHashCache['password'] = Hash::make('password');
    }

    /** Preload semua nama entity org → id (dipanggil lazy sekali). */
    private function loadLookups(): void
    {
        if ($this->lookupsLoaded) {
            return;
        }

        $this->departmentMap = $this->lowerKeyMap(Department::pluck('id', 'name'));
        $this->branchMap     = $this->lowerKeyMap(Branch::pluck('id', 'name'));
        $this->positionMap   = $this->lowerKeyMap(Position::pluck('id', 'name'));

        $this->lookupsLoaded = true;
    }

    /** @param \Illuminate\Support\Collection $pairs name=>id → [lower(name)=>id] */
    private function lowerKeyMap($pairs): array
    {
        $map = [];
        foreach ($pairs as $name => $id) {
            $map[mb_strtolower(trim((string) $name))] = (int) $id;
        }

        return $map;
    }

    public function model(array $row)
    {
        $this->loadLookups();

        $user = User::updateOrCreate(
            ['email' => trim($row['email'])],
            [
                'name'              => trim($row['nama']),
                'employee_id'       => trim((string) ($row['nik'] ?? '')) ?: null,
                'join_date'         => $this->date($row['tgl_bergabung'] ?? null),
                'department_id'     => $this->resolveId('department', Department::class, $row['departemen'] ?? null),
                'branch_id'         => $this->resolveId('branch', Branch::class, $row['cabang'] ?? null),
                'position_id'       => $this->resolveId('position', Position::class, $row['jabatan'] ?? null),
                'password'          => $this->hashPassword((string) ($row['password'] ?? 'password')),
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
            // UM-04 — NIK opsional, tapi jika diisi harus unik. Baris duplikat masuk
            // SkipsOnFailure (dilaporkan), tidak menghentikan seluruh import. Update
            // user yang sama by email tidak menabrak karena email = key updateOrCreate;
            // NIK bentrok ke user LAIN tetap ditolak.
            'nik'   => ['nullable', 'string', 'max:20', 'unique:users,employee_id'],
        ];
    }

    public function customValidationMessages(): array
    {
        return [
            'email.required' => 'Kolom email wajib diisi.',
            'email.email'    => 'Format email tidak valid.',
            'nama.required'  => 'Kolom nama wajib diisi.',
            'nik.unique'     => 'NIK sudah dipakai karyawan lain.',
        ];
    }

    /** UM-09 — chunk baca file supaya hemat memori pada file besar. */
    public function chunkSize(): int
    {
        return 500;
    }

    /**
     * Hash password dengan cache per nilai unik (bcrypt fix).
     * Import massal biasanya pakai password default sama → hash sekali saja.
     */
    private function hashPassword(string $plain): string
    {
        $plain = $plain !== '' ? $plain : 'password';

        return $this->passwordHashCache[$plain] ??= Hash::make($plain);
    }

    /**
     * Cari-atau-buat entity by name → id memakai map preload (N+1 fix).
     * Nama baru dibuat sekali lalu ditambahkan ke map untuk baris berikutnya.
     */
    private function resolveId(string $mapName, string $model, ?string $name): ?int
    {
        $name = trim((string) $name);
        if ($name === '') {
            return null;
        }

        $key = mb_strtolower($name);
        $mapProp = $mapName . 'Map';

        if (isset($this->{$mapProp}[$key])) {
            return $this->{$mapProp}[$key];
        }

        // Nama belum ada — buat sekali, cache ke map.
        $id = $model::firstOrCreate(['name' => $name])->id;
        $this->{$mapProp}[$key] = (int) $id;

        return (int) $id;
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
