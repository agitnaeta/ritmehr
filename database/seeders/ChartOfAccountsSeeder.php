<?php

namespace Database\Seeders;

use App\Models\Account;
use Illuminate\Database\Seeder;

/**
 * M12 — Default internal chart of accounts.
 *
 * Each account carries a functional ROLE so payroll/loan posting resolves the
 * right accounts directly from here — there is no separate mapping table. All
 * account management lives at /admin/account.
 *
 * Idempotent: safe to re-run.
 */
class ChartOfAccountsSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [
            // code, name, type, role, is_cash
            ['1000', 'Kas',                        Account::TYPE_ASSET,   Account::ROLE_CASH,            true],
            ['1100', 'Bank',                       Account::TYPE_ASSET,   null,                          true],
            ['1200', 'Piutang Karyawan (Kasbon)',  Account::TYPE_ASSET,   Account::ROLE_LOAN_RECEIVABLE, false],
            ['5000', 'Beban Gaji',                 Account::TYPE_EXPENSE, Account::ROLE_SALARY_EXPENSE,  false],
            // Kategori beban umum (biar user non-akuntan langsung bisa mencatat)
            ['5100', 'Beban Listrik',              Account::TYPE_EXPENSE, null, false],
            ['5200', 'Beban Air',                  Account::TYPE_EXPENSE, null, false],
            ['5300', 'Beban Sewa',                 Account::TYPE_EXPENSE, null, false],
            ['5400', 'Beban ATK & Perlengkapan',   Account::TYPE_EXPENSE, null, false],
            ['5900', 'Beban Lain-lain',            Account::TYPE_EXPENSE, null, false],
            // Kategori pendapatan umum
            ['4000', 'Pendapatan Jasa',            Account::TYPE_INCOME,  null, false],
            ['4900', 'Pendapatan Lain-lain',       Account::TYPE_INCOME,  null, false],
        ];

        foreach ($accounts as [$code, $name, $type, $role, $isCash]) {
            Account::updateOrCreate(
                ['code' => $code],
                ['name' => $name, 'type' => $type, 'role' => $role, 'is_cash' => $isCash, 'is_active' => true]
            );
        }

        $this->command?->info('Chart of accounts (roles, kas/bank, kategori umum) seeded.');
    }
}
