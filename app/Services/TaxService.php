<?php

namespace App\Services;

use App\Models\BpjsRate;
use App\Models\EmployeeTaxProfile;
use App\Models\Pph21Bracket;
use App\Models\PtkpRate;
use App\Models\SalaryRecap;
use App\Models\TerRate;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Indonesian payroll statutory calculations: BPJS contributions, PPh 21, THR.
 *
 * Rates are not hard-coded — PTKP, tax brackets and BPJS percentages all live
 * in database tables keyed by year, because the government revises them and a
 * historical recalculation must use the rates of its own period.
 */
class TaxService
{
    /** Biaya jabatan: 5% of gross, capped at Rp 6,000,000/year. */
    private const OCCUPATIONAL_COST_RATE = 0.05;
    private const OCCUPATIONAL_COST_ANNUAL_CAP = 6_000_000;

    /** Surcharge applied to employees without an NPWP. */
    private const NO_NPWP_SURCHARGE = 0.20;

    /**
     * M19 — Mapping of PTKP status → TER category (PMK 168/2023).
     *   A = TK/0, TK/1, K/0
     *   B = TK/2, TK/3, K/1, K/2
     *   C = K/3
     * K/I/n (combined spouse income) is a year-end SPT matter, not monthly
     * withholding by one employer; we withhold monthly following the K/n row.
     */
    public const TER_CATEGORY = [
        'TK/0' => 'A', 'TK/1' => 'A', 'K/0' => 'A',
        'TK/2' => 'B', 'TK/3' => 'B', 'K/1' => 'B', 'K/2' => 'B',
        'K/3'  => 'C',
        'K/I/0' => 'A', 'K/I/1' => 'B', 'K/I/2' => 'B', 'K/I/3' => 'C',
    ];

    // ── BPJS ───────────────────────────────────────────────

    /**
     * All BPJS components for a monthly base salary.
     *
     * @return array{
     *   kes_employee:int, kes_employer:int,
     *   jht_employee:int, jht_employer:int,
     *   jp_employee:int,  jp_employer:int,
     *   jkk:int, jkm:int,
     *   employee_total:int, employer_total:int
     * }
     */
    public function calculateBPJS(User $user, int $baseSalary, ?int $year = null): array
    {
        $year ??= (int) now()->year;
        $profile = $this->profileFor($user);
        $rates = $this->bpjsRates($year);

        $result = [
            'kes_employee' => 0, 'kes_employer' => 0,
            'jht_employee' => 0, 'jht_employer' => 0,
            'jp_employee'  => 0, 'jp_employer'  => 0,
            'jkk'          => 0, 'jkm'          => 0,
        ];

        if ($baseSalary > 0 && $profile->bpjs_kesehatan && $kes = $rates->get(BpjsRate::TYPE_KESEHATAN)) {
            $base = $kes->cappedBase($baseSalary);
            $result['kes_employee'] = $this->pct($base, $kes->employee_rate);
            $result['kes_employer'] = $this->pct($base, $kes->employer_rate);
        }

        // The three Ketenagakerjaan programmes are each individually togglable
        // but all sit under the master bpjs_ketenagakerjaan switch.
        if ($baseSalary > 0 && $profile->bpjs_ketenagakerjaan) {
            if ($profile->bpjs_tk_jht && $jht = $rates->get(BpjsRate::TYPE_JHT)) {
                $base = $jht->cappedBase($baseSalary);
                $result['jht_employee'] = $this->pct($base, $jht->employee_rate);
                $result['jht_employer'] = $this->pct($base, $jht->employer_rate);
            }

            if ($profile->bpjs_tk_jp && $jp = $rates->get(BpjsRate::TYPE_JP)) {
                $base = $jp->cappedBase($baseSalary);
                $result['jp_employee'] = $this->pct($base, $jp->employee_rate);
                $result['jp_employer'] = $this->pct($base, $jp->employer_rate);
            }

            // JKK and JKM are employer-only.
            if ($profile->bpjs_tk_jkk && $jkk = $rates->get(BpjsRate::TYPE_JKK)) {
                $result['jkk'] = $this->pct($jkk->cappedBase($baseSalary), $jkk->employer_rate);
            }

            if ($profile->bpjs_tk_jkm && $jkm = $rates->get(BpjsRate::TYPE_JKM)) {
                $result['jkm'] = $this->pct($jkm->cappedBase($baseSalary), $jkm->employer_rate);
            }
        }

        $result['employee_total'] = $result['kes_employee'] + $result['jht_employee'] + $result['jp_employee'];
        $result['employer_total'] = $result['kes_employer'] + $result['jht_employer']
                                    + $result['jp_employer'] + $result['jkk'] + $result['jkm'];

        return $result;
    }

    // ── PPh 21 ─────────────────────────────────────────────

    /**
     * Monthly PPh 21 using the annualised method.
     *
     * Annual gross is projected from the month, reduced by biaya jabatan, the
     * employee's JHT/JP contributions and PTKP; progressive brackets are then
     * applied and the result divided back to a month.
     *
     * @param  int  $grossMonthly  taxable monthly income (excludes reimbursements)
     */
    public function calculatePPh21(User $user, int $grossMonthly, ?int $year = null): int
    {
        $year ??= (int) now()->year;

        if ($grossMonthly <= 0) {
            return 0;
        }

        $annualGross = $grossMonthly * 12;

        // 1. Biaya jabatan — 5% of gross, capped.
        $occupationalCost = min(
            (int) round($annualGross * self::OCCUPATIONAL_COST_RATE),
            self::OCCUPATIONAL_COST_ANNUAL_CAP
        );

        // 2. Employee-borne JHT and JP are deductible; health insurance is not.
        $bpjs = $this->calculateBPJS($user, $grossMonthly, $year);
        $deductibleBpjs = ($bpjs['jht_employee'] + $bpjs['jp_employee']) * 12;

        $netAnnual = $annualGross - $occupationalCost - $deductibleBpjs;

        // 3. PTKP for the employee's tax status.
        $ptkp = $this->getApplicablePTKP($user, $year);
        $taxable = $netAnnual - $ptkp;

        if ($taxable <= 0) {
            return 0;
        }

        // PKP is rounded down to the nearest thousand rupiah.
        $taxable = (int) (floor($taxable / 1000) * 1000);

        $annualTax = $this->applyBrackets($taxable, $year);

        if (! $this->profileFor($user)->hasNpwp()) {
            $annualTax = (int) round($annualTax * (1 + self::NO_NPWP_SURCHARGE));
        }

        return (int) round($annualTax / 12);
    }

    /**
     * Progressive tax across the year's brackets.
     */
    public function applyBrackets(int $taxableIncome, int $year): int
    {
        if ($taxableIncome <= 0) {
            return 0;
        }

        $brackets = Pph21Bracket::forYear($year)->get();

        if ($brackets->isEmpty()) {
            // No configured brackets means no basis to tax on — returning zero
            // is safer than inventing a rate.
            return 0;
        }

        $tax = 0;

        foreach ($brackets as $bracket) {
            if ($taxableIncome <= $bracket->lower_bound) {
                break;
            }

            $upper = $bracket->upper_bound ?? $taxableIncome;
            $slice = min($taxableIncome, $upper) - $bracket->lower_bound;

            if ($slice > 0) {
                $tax += $slice * ($bracket->rate / 100);
            }
        }

        return (int) round($tax);
    }

    public function getApplicablePTKP(User $user, ?int $year = null): int
    {
        $year ??= (int) now()->year;
        $status = $this->profileFor($user)->tax_status;

        $rate = PtkpRate::where('year', $year)->where('status', $status)->first();

        if ($rate) {
            return (int) $rate->amount;
        }

        // Fall back to the most recent year on file rather than taxing the
        // full amount because this year's table has not been entered yet.
        $fallback = PtkpRate::where('status', $status)
            ->where('year', '<', $year)
            ->orderByDesc('year')
            ->first();

        return (int) ($fallback->amount ?? 0);
    }

    // ── PPh 21 TER (M19, since 2024 / PP 58/2023) ──────────

    /**
     * TER category (A/B/C) for a PTKP status. Falls back to A with a warning
     * for unknown statuses rather than throwing mid-payroll.
     */
    public function terCategory(string $status): string
    {
        if (isset(self::TER_CATEGORY[$status])) {
            return self::TER_CATEGORY[$status];
        }

        \Illuminate\Support\Facades\Log::channel('daily_log')
            ->warning("[TER] unknown tax_status '{$status}', defaulting to category A.");

        return 'A';
    }

    /**
     * Monthly PPh 21 for Masa Pajak January–November using the TER method:
     * effective rate × gross, no monthly deduction of biaya jabatan/PTKP (those
     * are baked into the effective rate). The no-NPWP surcharge still applies.
     *
     * Returns 0 (and logs) when no TER table is configured for the year, rather
     * than inventing a rate.
     *
     * @param  int  $grossMonthly  gross taxable income for the month
     */
    public function calculatePPh21TER(User $user, int $grossMonthly, ?int $year = null): int
    {
        $year ??= (int) now()->year;

        if ($grossMonthly <= 0) {
            return 0;
        }

        $category = $this->terCategory($this->profileFor($user)->tax_status);
        $rate = TerRate::rateFor($year, $category, $grossMonthly);

        if ($rate === null) {
            \Illuminate\Support\Facades\Log::channel('daily_log')
                ->warning("[TER] no rate table for year {$year} category {$category}; PPh21 monthly = 0.");
            return 0;
        }

        $tax = (int) round($grossMonthly * $rate / 100);

        if (! $this->profileFor($user)->hasNpwp()) {
            $tax = (int) round($tax * (1 + self::NO_NPWP_SURCHARGE));
        }

        return $tax;
    }

    /**
     * Masa Pajak December (or the employee's final month): reconcile the whole
     * year with the progressive Pasal 17 method against the actual accumulated
     * gross, then subtract what was already withheld Jan–Nov. May be negative
     * (over-withheld → refunded on the December slip).
     *
     * @param  int  $annualGross      actual accumulated gross Jan–Dec
     * @param  int  $withheldToDate   sum of PPh 21 already withheld Jan–Nov
     */
    public function calculateDecemberCorrection(
        User $user,
        int $annualGross,
        int $withheldToDate,
        ?int $year = null
    ): int {
        $year ??= (int) now()->year;

        if ($annualGross <= 0) {
            return -$withheldToDate; // refund anything already taken
        }

        // Biaya jabatan — 5% of gross, capped.
        $occupationalCost = min(
            (int) round($annualGross * self::OCCUPATIONAL_COST_RATE),
            self::OCCUPATIONAL_COST_ANNUAL_CAP
        );

        // Employee-borne JHT + JP for the year are deductible; health is not.
        // Derive from the monthly base for a stable annual figure.
        $monthlyBase = (int) ($user->salary->amount ?? 0);
        $bpjs = $this->calculateBPJS($user, $monthlyBase, $year);
        $deductibleBpjs = ($bpjs['jht_employee'] + $bpjs['jp_employee']) * 12;

        $netAnnual = $annualGross - $occupationalCost - $deductibleBpjs;
        $ptkp = $this->getApplicablePTKP($user, $year);
        $taxable = $netAnnual - $ptkp;

        if ($taxable <= 0) {
            return -$withheldToDate;
        }

        $taxable = (int) (floor($taxable / 1000) * 1000);
        $annualTax = $this->applyBrackets($taxable, $year);

        if (! $this->profileFor($user)->hasNpwp()) {
            $annualTax = (int) round($annualTax * (1 + self::NO_NPWP_SURCHARGE));
        }

        return $annualTax - $withheldToDate;
    }

    // ── THR ────────────────────────────────────────────────

    /**
     * Religious holiday allowance. A full month's salary after 12 months of
     * service, prorated by month for shorter tenure; nothing under one month.
     */
    public function calculateTHR(User $user, ?int $monthlySalary = null, ?Carbon $asOf = null): int
    {
        $asOf ??= now();
        $monthlySalary ??= (int) ($user->salary->amount ?? 0);

        if ($monthlySalary <= 0 || ! $user->join_date) {
            return 0;
        }

        $months = $user->monthsOfService($asOf);

        if ($months < 1) {
            return 0;
        }

        if ($months >= 12) {
            return $monthlySalary;
        }

        return (int) round($monthlySalary * $months / 12);
    }

    // ── Applying to a recap ────────────────────────────────

    /**
     * Compute and store statutory figures on a salary recap.
     *
     * Writes quietly: the recap observer recalculates on save and would
     * otherwise recurse.
     */
    public function applyToRecap(SalaryRecap $recap): SalaryRecap
    {
        $user = User::with('salary')->find($recap->user_id);

        if (! $user) {
            return $recap;
        }

        $year = $this->recapYear($recap);
        $month = $this->recapMonth($recap);

        $gross = (int) $recap->salary_amount
               + (int) $recap->overtime_amount
               + (int) $recap->extra_time_amount
               + (int) $recap->thr
               + (int) $recap->bonus;

        $bpjs = $this->calculateBPJS($user, (int) $recap->salary_amount, $year);

        // M19 — PPh 21 uses TER for Jan–Nov and a progressive year-end
        // reconciliation for December (Masa Pajak terakhir).
        if ($month === 12) {
            $annualGross = $this->accumulatedGross($user, $year) + $gross;
            $withheld = $this->accumulatedPph21($user, $year);
            $pph21 = $this->calculateDecemberCorrection($user, $annualGross, $withheld, $year);
        } else {
            $pph21 = $this->calculatePPh21TER($user, $gross, $year);
        }

        $otherDeductions = (int) $recap->loan_cut + (int) $recap->late_cut + (int) $recap->abstain_cut;
        $net = $gross - $otherDeductions - $bpjs['employee_total'] - $pph21;

        $recap->forceFill([
            'gross_income'      => $gross,
            'pph21'             => $pph21,
            'bpjs_kes_employee' => $bpjs['kes_employee'],
            'bpjs_kes_employer' => $bpjs['kes_employer'],
            'bpjs_jht_employee' => $bpjs['jht_employee'],
            'bpjs_jht_employer' => $bpjs['jht_employer'],
            'bpjs_jp_employee'  => $bpjs['jp_employee'],
            'bpjs_jp_employer'  => $bpjs['jp_employer'],
            'bpjs_jkk'          => $bpjs['jkk'],
            'bpjs_jkm'          => $bpjs['jkm'],
            'net_income'        => max(0, $net),
        ])->saveQuietly();

        return $recap;
    }

    // ── Reporting ──────────────────────────────────────────

    /**
     * Per-employee PPh 21 totals for a year — the basis for SPT reporting.
     */
    public function generateAnnualTaxReport(int $year): Collection
    {
        return SalaryRecap::with('user.department')
            ->where('recap_month', 'like', '%-' . $year)
            ->get()
            ->groupBy('user_id')
            ->map(function ($recaps) {
                $user = $recaps->first()->user;

                return [
                    'user'         => $user,
                    'npwp'         => $user?->taxProfile?->npwp,
                    'tax_status'   => $user?->taxProfile?->tax_status,
                    'department'   => $user?->department?->name ?? '—',
                    'gross'        => (int) $recaps->sum('gross_income'),
                    'pph21'        => (int) $recaps->sum('pph21'),
                    'bpjs_employee' => (int) $recaps->sum(fn ($r) => $r->bpjs_kes_employee
                                                                   + $r->bpjs_jht_employee
                                                                   + $r->bpjs_jp_employee),
                    'net'          => (int) $recaps->sum('net_income'),
                    'months'       => $recaps->count(),
                ];
            })
            ->sortByDesc('gross')
            ->values();
    }

    /**
     * Monthly BPJS totals, in the shape needed for the BPJS submission.
     */
    public function generateBpjsReport(string $recapMonth): Collection
    {
        return SalaryRecap::with('user')
            ->where('recap_month', $recapMonth)
            ->get()
            ->map(fn (SalaryRecap $r) => [
                'user'           => $r->user,
                'base'           => (int) $r->salary_amount,
                'kes_employee'   => (int) $r->bpjs_kes_employee,
                'kes_employer'   => (int) $r->bpjs_kes_employer,
                'jht_employee'   => (int) $r->bpjs_jht_employee,
                'jht_employer'   => (int) $r->bpjs_jht_employer,
                'jp_employee'    => (int) $r->bpjs_jp_employee,
                'jp_employer'    => (int) $r->bpjs_jp_employer,
                'jkk'            => (int) $r->bpjs_jkk,
                'jkm'            => (int) $r->bpjs_jkm,
                'employee_total' => (int) ($r->bpjs_kes_employee + $r->bpjs_jht_employee + $r->bpjs_jp_employee),
                'employer_total' => (int) ($r->bpjs_kes_employer + $r->bpjs_jht_employer
                                           + $r->bpjs_jp_employer + $r->bpjs_jkk + $r->bpjs_jkm),
            ])
            ->sortBy(fn ($row) => $row['user']?->name ?? '')
            ->values();
    }

    // ── Internals ──────────────────────────────────────────

    /**
     * Tax settings for a user, defaulting to TK/0 with all BPJS enabled when
     * HR has not filled in a profile yet.
     */
    public function profileFor(User $user): EmployeeTaxProfile
    {
        return $user->taxProfile ?? new EmployeeTaxProfile([
            'user_id'              => $user->id,
            'tax_status'           => 'TK/0',
            'tax_method'           => 'gross',
            'bpjs_kesehatan'       => true,
            'bpjs_ketenagakerjaan' => true,
            'bpjs_tk_jht'          => true,
            'bpjs_tk_jp'           => true,
            'bpjs_tk_jkk'          => true,
            'bpjs_tk_jkm'          => true,
        ]);
    }

    /**
     * @return Collection<string, BpjsRate> keyed by type
     */
    private function bpjsRates(int $year): Collection
    {
        $rates = BpjsRate::forYear($year)->get()->keyBy('type');

        if ($rates->isNotEmpty()) {
            return $rates;
        }

        // Same reasoning as PTKP: prefer last year's published rates over
        // silently contributing nothing.
        $latestYear = BpjsRate::where('year', '<', $year)->max('year');

        return $latestYear
            ? BpjsRate::forYear((int) $latestYear)->get()->keyBy('type')
            : collect();
    }

    private function recapYear(SalaryRecap $recap): int
    {
        try {
            return (int) Carbon::createFromFormat('m-Y', $recap->recap_month)->year;
        } catch (\Throwable $e) {
            return (int) now()->year;
        }
    }

    /** Month number (1–12) parsed from the recap's m-Y label. */
    private function recapMonth(SalaryRecap $recap): int
    {
        try {
            return (int) Carbon::createFromFormat('m-Y', $recap->recap_month)->month;
        } catch (\Throwable $e) {
            return (int) now()->month;
        }
    }

    /** Sum of gross income across a user's recaps for a year (excludes December). */
    private function accumulatedGross(User $user, int $year): int
    {
        return (int) SalaryRecap::where('user_id', $user->id)
            ->where('recap_month', 'like', '%-' . $year)
            ->where('recap_month', 'not like', '12-%')
            ->sum('gross_income');
    }

    /** Sum of PPh 21 already withheld across a user's recaps for a year (excludes December). */
    private function accumulatedPph21(User $user, int $year): int
    {
        return (int) SalaryRecap::where('user_id', $user->id)
            ->where('recap_month', 'like', '%-' . $year)
            ->where('recap_month', 'not like', '12-%')
            ->sum('pph21');
    }

    private function pct(int $base, float $rate): int
    {
        return (int) round($base * $rate / 100);
    }
}
