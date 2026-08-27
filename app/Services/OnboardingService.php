<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\CompanyProfile;
use App\Models\Department;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

/**
 * WIZ-03 — Logika Setup Wizard onboarding.
 *
 * Tiap step memvalidasi input lalu MERGE LANGSUNG ke DB (tanpa tabel perantara).
 * Status selesai disimpan di tabel settings (key: onboarding_complete).
 */
class OnboardingService
{
    private const STEPS = ['company', 'orgunit', 'admin', 'import'];
    private const FLAG = 'onboarding_complete';

    public function __construct(private readonly SettingService $settings) {}

    /** Data pendukung untuk render satu step. */
    public function context(string $step): array
    {
        return match ($step) {
            'company' => ['company' => CompanyProfile::first()],
            'orgunit' => ['departments' => Department::pluck('name'), 'branches' => Branch::pluck('name')],
            'admin'   => ['me' => backpack_user(), 'departments' => Department::orderBy('name')->get()],
            default   => [],
        };
    }

    /** Validasi + simpan satu step ke DB. */
    public function save(string $step, array $input): void
    {
        match ($step) {
            'company' => $this->saveCompany($input),
            'orgunit' => $this->saveOrgUnit($input),
            'admin'   => $this->saveAdmin($input),
            'import'  => null, // file di-handle controller (reuse UserImport)
            default   => null,
        };
    }

    public function nextStep(string $step): ?string
    {
        $i = array_search($step, self::STEPS, true);

        return self::STEPS[$i + 1] ?? null;
    }

    public function isComplete(): bool
    {
        return (bool) $this->settings->get(self::FLAG, false);
    }

    public function markComplete(): void
    {
        $this->settings->set(self::FLAG, true);
        $this->settings->flush();
    }

    // ── per step ───────────────────────────────────────────

    private function saveCompany(array $in): void
    {
        Validator::make($in, ['name' => 'required|string|max:255'])->validate();

        $company = CompanyProfile::first() ?? new CompanyProfile();
        $company->name = $in['name'];
        $company->address = $in['address'] ?? null;
        $company->phone = $in['phone'] ?? null;
        $company->save();
    }

    private function saveOrgUnit(array $in): void
    {
        $departments = array_filter(array_map('trim', (array) ($in['departments'] ?? [])));
        $branches = array_filter(array_map('trim', (array) ($in['branches'] ?? [])));

        Validator::make(
            ['departments' => $departments, 'branches' => $branches],
            ['departments' => 'required|array|min:1', 'branches' => 'required|array|min:1'],
            [
                'departments.required' => 'Minimal satu departemen wajib diisi.',
                'branches.required'    => 'Minimal satu cabang wajib diisi.',
            ]
        )->validate();

        $company = CompanyProfile::first();
        foreach ($departments as $name) {
            Department::firstOrCreate(['name' => $name]);
        }
        foreach ($branches as $name) {
            Branch::firstOrCreate(['name' => $name], ['company_profile_id' => $company?->id, 'is_active' => true]);
        }
    }

    private function saveAdmin(array $in): void
    {
        Validator::make($in, [
            'name'  => 'required|string|max:255',
            'email' => 'required|email',
        ])->validate();

        $me = backpack_user();
        if ($me) {
            $me->update([
                'name'          => $in['name'],
                'email'         => $in['email'],
                'department_id' => $in['department_id'] ?? $me->department_id,
            ]);
        }

        // Opsional: buat akun HR terpisah.
        if (! empty($in['create_hr']) && ! empty($in['hr_email'])) {
            $hr = User::firstOrCreate(
                ['email' => $in['hr_email']],
                ['name' => $in['hr_name'] ?? 'HR Admin', 'password' => Hash::make($in['hr_password'] ?? 'password')]
            );
            if (method_exists($hr, 'assignRole')) {
                $hr->assignRole('hr_admin');
            }
        }
    }
}
