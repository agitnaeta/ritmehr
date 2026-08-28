<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Demo accounting data — a small, BALANCED set of journal entries so the
 * financial reports (buku besar, neraca saldo, neraca) have realistic figures
 * for screenshots and evaluation.
 *
 * The story (one operating month):
 *   - Setoran modal awal ke bank
 *   - Pendapatan jasa diterima di bank
 *   - Beban gaji, sewa, listrik dibayar
 *   - Tarik tunai bank → kas, lalu kasbon karyawan & beban listrik dari kas
 *   - Pembelian ATK secara kredit → menimbulkan utang usaha
 *
 * Result (seimbang): Aset = Rp 115.000.000 = Kewajiban Rp 1.500.000 +
 * Ekuitas Rp 113.500.000 (Modal Rp 100.000.000 + Laba berjalan Rp 13.500.000).
 *
 * Idempotent: all entries carry reference 'DEMO-ACC'; a re-run clears and
 * rebuilds only those, never touching payroll/loan auto-posted journals.
 *
 * NOT for production.
 */
class DemoAccountingSeeder extends Seeder
{
    private const REF = 'DEMO-ACC';

    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command?->error('Refusing to seed demo accounting in production.');

            return;
        }

        // 1) Pastikan akun modal & utang usaha ada (bagan default tak punya
        //    equity/liability, sedangkan neraca membutuhkannya).
        $extra = [
            // code, name, type
            ['2000', 'Utang Usaha',   Account::TYPE_LIABILITY],
            ['3000', 'Modal Disetor', Account::TYPE_EQUITY],
        ];
        foreach ($extra as [$code, $name, $type]) {
            Account::updateOrCreate(
                ['code' => $code],
                ['name' => $name, 'type' => $type, 'role' => null, 'is_cash' => false, 'is_active' => true]
            );
        }

        $acc = Account::pluck('id', 'code'); // code => id

        // 2) Bersihkan jurnal demo lama (idempoten) — hanya milik seeder ini.
        JournalEntry::where('reference', 'like', self::REF . '%')->each(function (JournalEntry $e) {
            $e->lines()->delete();
            $e->delete();
        });

        $month = now()->subMonthNoOverflow()->startOfMonth();
        $d = fn (int $day) => $month->copy()->addDays($day - 1)->toDateString();

        // 3) Jurnal berimbang. Tiap item: [tanggal, deskripsi, kind, [ [akun, debit, kredit], ... ]]
        $entries = [
            [$d(1),  'Setoran modal awal pemilik',        JournalEntry::KIND_GENERAL, [
                ['1100', 100_000_000, 0], ['3000', 0, 100_000_000],
            ]],
            [$d(5),  'Penerimaan pendapatan jasa proyek',  JournalEntry::KIND_INCOME, [
                ['1100', 90_000_000, 0], ['4000', 0, 90_000_000],
            ]],
            [$d(25), 'Pembayaran gaji karyawan',           JournalEntry::KIND_EXPENSE, [
                ['5000', 62_500_000, 0], ['1100', 0, 62_500_000],
            ]],
            [$d(3),  'Pembayaran sewa kantor',             JournalEntry::KIND_EXPENSE, [
                ['5300', 10_000_000, 0], ['1100', 0, 10_000_000],
            ]],
            [$d(10), 'Tarik tunai bank ke kas',            JournalEntry::KIND_TRANSFER, [
                ['1000', 10_000_000, 0], ['1100', 0, 10_000_000],
            ]],
            [$d(12), 'Pembayaran tagihan listrik',         JournalEntry::KIND_EXPENSE, [
                ['5100', 2_500_000, 0], ['1000', 0, 2_500_000],
            ]],
            [$d(15), 'Pencairan kasbon karyawan (Ahmad)',  JournalEntry::KIND_GENERAL, [
                ['1200', 3_000_000, 0], ['1000', 0, 3_000_000],
            ]],
            [$d(20), 'Pembelian ATK & perlengkapan (kredit)', JournalEntry::KIND_EXPENSE, [
                ['5400', 1_500_000, 0], ['2000', 0, 1_500_000],
            ]],
        ];

        DB::transaction(function () use ($entries, $acc) {
            foreach ($entries as $i => [$date, $desc, $kind, $lines]) {
                $entry = JournalEntry::create([
                    'date'        => $date,
                    'description' => $desc,
                    'source'      => JournalEntry::SOURCE_MANUAL,
                    'kind'        => $kind,
                    'reference'   => self::REF . '-' . str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT),
                ]);

                foreach ($lines as [$code, $debit, $credit]) {
                    JournalLine::create([
                        'journal_entry_id' => $entry->id,
                        'account_id'       => $acc[$code],
                        'debit'            => $debit,
                        'credit'           => $credit,
                    ]);
                }
            }
        });

        $this->command?->info('Demo accounting seeded: 8 balanced entries (Aset Rp 115.000.000, seimbang).');
    }
}
