<?php

namespace App\Services;

use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * M12 — Manual journal management.
 *
 * Manual entries (source=manual) are for anything outside payroll/loans:
 * expenses (listrik, sewa), opening balances, adjustments, corrections. They
 * can be edited/deleted freely. Auto-posted entries are locked and can only be
 * corrected via a reversal (a mirror-image entry) so the audit trail is kept.
 */
class JournalService
{
    /** Default disk for transaction proof (nota/kwitansi). */
    public const DISK = 'local';
    private const ATTACH_DIR = 'journal-attachments';

    public function __construct(private readonly StorageManager $storage)
    {
    }

    /** Active filesystem for journal attachments (M16 pluggable storage). */
    public function disk(): \Illuminate\Contracts\Filesystem\Filesystem
    {
        return $this->storage->disk();
    }

    // ── Friendly "Catat Transaksi" modes ───────────────────

    /**
     * Create a journal from one of the simple, human-language modes. The user
     * gives ONE amount and picks accounts by purpose; we build the two balanced
     * lines behind the scenes so it can never be lopsided.
     *
     * @param string $kind expense|income|transfer
     * @param array{date:string, description:string, amount:float|int|string,
     *              cash_account_id?:int, category_account_id?:int,
     *              from_account_id?:int, to_account_id?:int} $data
     */
    public function createSimple(string $kind, array $data, ?UploadedFile $file = null): JournalEntry
    {
        [$debitId, $creditId, $amount] = $this->resolveSimple($kind, $data);

        return DB::transaction(function () use ($kind, $data, $debitId, $creditId, $amount, $file) {
            $entry = JournalEntry::create([
                'date'        => $data['date'],
                'description' => $data['description'],
                'source'      => JournalEntry::SOURCE_MANUAL,
                'kind'        => $kind,
                'reference'   => null,
            ]);

            $this->writeTwoLines($entry, $debitId, $creditId, $amount);

            if ($file) {
                $this->attachFile($entry, $file);
            }

            return $entry->load('lines');
        });
    }

    public function updateSimple(JournalEntry $entry, string $kind, array $data, ?UploadedFile $file = null, bool $removeAttachment = false): JournalEntry
    {
        $this->assertManual($entry);
        [$debitId, $creditId, $amount] = $this->resolveSimple($kind, $data);

        return DB::transaction(function () use ($entry, $kind, $data, $debitId, $creditId, $amount, $file, $removeAttachment) {
            $entry->update([
                'date'        => $data['date'],
                'description' => $data['description'],
                'kind'        => $kind,
            ]);

            $entry->lines()->delete();
            $this->writeTwoLines($entry, $debitId, $creditId, $amount);

            if ($removeAttachment) {
                $this->deleteAttachment($entry);
            }
            if ($file) {
                $this->attachFile($entry, $file);
            }

            return $entry->load('lines');
        });
    }

    /**
     * Translate a simple mode into [debitAccountId, creditAccountId, amount].
     * Posting logic: debit destination, credit source.
     */
    private function resolveSimple(string $kind, array $data): array
    {
        $amount = (float) ($data['amount'] ?? 0);
        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => 'Jumlah harus lebih dari nol.']);
        }

        switch ($kind) {
            case JournalEntry::KIND_EXPENSE:
                // Uang keluar: beban (debit) ← kas (credit)
                $cash = (int) ($data['cash_account_id'] ?? 0);
                $cat = (int) ($data['category_account_id'] ?? 0);
                $this->assertAccounts([$cash, $cat]);
                return [$cat, $cash, $amount];

            case JournalEntry::KIND_INCOME:
                // Uang masuk: kas (debit) ← pendapatan (credit)
                $cash = (int) ($data['cash_account_id'] ?? 0);
                $cat = (int) ($data['category_account_id'] ?? 0);
                $this->assertAccounts([$cash, $cat]);
                return [$cash, $cat, $amount];

            case JournalEntry::KIND_TRANSFER:
                // Pindah dana: tujuan (debit) ← asal (credit)
                $from = (int) ($data['from_account_id'] ?? 0);
                $to = (int) ($data['to_account_id'] ?? 0);
                $this->assertAccounts([$from, $to]);
                if ($from === $to) {
                    throw ValidationException::withMessages(['to_account_id' => 'Akun asal dan tujuan tidak boleh sama.']);
                }
                return [$to, $from, $amount];

            default:
                throw ValidationException::withMessages(['kind' => 'Jenis transaksi tidak dikenal.']);
        }
    }

    private function assertAccounts(array $ids): void
    {
        foreach ($ids as $id) {
            if ($id <= 0) {
                throw ValidationException::withMessages(['account' => 'Akun wajib dipilih.']);
            }
        }
    }

    private function writeTwoLines(JournalEntry $entry, int $debitId, int $creditId, float $amount): void
    {
        JournalLine::create([
            'journal_entry_id' => $entry->id, 'account_id' => $debitId,
            'debit' => $amount, 'credit' => 0,
        ]);
        JournalLine::create([
            'journal_entry_id' => $entry->id, 'account_id' => $creditId,
            'debit' => 0, 'credit' => $amount,
        ]);
    }

    // ── Attachments ────────────────────────────────────────

    public function attachFile(JournalEntry $entry, UploadedFile $file): void
    {
        $this->deleteAttachment($entry);
        $path = $this->disk()->putFile(self::ATTACH_DIR, $file);
        $entry->update([
            'attachment_path' => $path,
            'attachment_name' => $file->getClientOriginalName(),
        ]);
    }

    public function deleteAttachment(JournalEntry $entry): void
    {
        $disk = $this->disk();
        if ($entry->attachment_path && $disk->exists($entry->attachment_path)) {
            $disk->delete($entry->attachment_path);
        }
        $entry->update(['attachment_path' => null, 'attachment_name' => null]);
    }

    // ── Advanced (accountant) mode ─────────────────────────

    /**
     * @param array<int, array{account_id:int|string, debit:float|int|string, credit:float|int|string}> $lines
     */
    public function createManual(string $date, string $description, array $lines): JournalEntry
    {
        $clean = $this->validateLines($lines);

        return DB::transaction(function () use ($date, $description, $clean) {
            $entry = JournalEntry::create([
                'date'        => $date,
                'description' => $description,
                'source'      => JournalEntry::SOURCE_MANUAL,
                'reference'   => null,
            ]);

            foreach ($clean as $line) {
                JournalLine::create([
                    'journal_entry_id' => $entry->id,
                    'account_id'       => $line['account_id'],
                    'debit'            => $line['debit'],
                    'credit'           => $line['credit'],
                ]);
            }

            return $entry->load('lines');
        });
    }

    /**
     * @param array<int, array{account_id:int|string, debit:float|int|string, credit:float|int|string}> $lines
     */
    public function updateManual(JournalEntry $entry, string $date, string $description, array $lines): JournalEntry
    {
        $this->assertManual($entry);
        $clean = $this->validateLines($lines);

        return DB::transaction(function () use ($entry, $date, $description, $clean) {
            $entry->update(['date' => $date, 'description' => $description]);
            $entry->lines()->delete();

            foreach ($clean as $line) {
                JournalLine::create([
                    'journal_entry_id' => $entry->id,
                    'account_id'       => $line['account_id'],
                    'debit'            => $line['debit'],
                    'credit'           => $line['credit'],
                ]);
            }

            return $entry->load('lines');
        });
    }

    public function deleteManual(JournalEntry $entry): void
    {
        $this->assertManual($entry);
        $this->deleteAttachment($entry);
        $entry->delete(); // cascade removes lines
    }

    /**
     * Reverse any entry by creating a mirror-image entry (debits↔credits).
     * Used to correct locked (auto-posted) entries without touching them.
     */
    public function reverse(JournalEntry $entry): JournalEntry
    {
        if ($entry->isReversal()) {
            throw ValidationException::withMessages(['entry' => 'Jurnal pembalik tidak bisa dibalik lagi.']);
        }
        if ($entry->isReversed()) {
            throw ValidationException::withMessages(['entry' => 'Jurnal ini sudah pernah dibalik.']);
        }

        return DB::transaction(function () use ($entry) {
            $reversal = JournalEntry::create([
                'date'              => now()->toDateString(),
                'description'       => 'Pembalik: ' . $entry->description,
                'source'            => JournalEntry::SOURCE_MANUAL,
                'reversed_entry_id' => $entry->id,
            ]);

            foreach ($entry->lines as $line) {
                JournalLine::create([
                    'journal_entry_id' => $reversal->id,
                    'account_id'       => $line->account_id,
                    'debit'            => $line->credit, // swapped
                    'credit'           => $line->debit,  // swapped
                ]);
            }

            return $reversal->load('lines');
        });
    }

    private function assertManual(JournalEntry $entry): void
    {
        if ($entry->isLocked()) {
            throw ValidationException::withMessages([
                'entry' => 'Jurnal otomatis (dari gaji/kasbon) tidak bisa diubah/dihapus. Gunakan jurnal pembalik untuk koreksi.',
            ]);
        }
    }

    /**
     * Normalise + validate: at least 2 lines, non-negative amounts, each line is
     * either a debit or a credit (not both), and total debit == total credit.
     *
     * @return array<int, array{account_id:int, debit:float, credit:float}>
     */
    private function validateLines(array $lines): array
    {
        $clean = [];
        $totalDebit = 0.0;
        $totalCredit = 0.0;

        foreach ($lines as $line) {
            $accountId = (int) ($line['account_id'] ?? 0);
            $debit = (float) ($line['debit'] ?? 0);
            $credit = (float) ($line['credit'] ?? 0);

            if ($accountId <= 0) {
                continue; // skip empty rows
            }
            if ($debit < 0 || $credit < 0) {
                throw ValidationException::withMessages(['lines' => 'Nilai debit/kredit tidak boleh negatif.']);
            }
            if ($debit > 0 && $credit > 0) {
                throw ValidationException::withMessages(['lines' => 'Satu baris hanya boleh debit ATAU kredit, tidak keduanya.']);
            }
            if ($debit == 0 && $credit == 0) {
                continue; // skip zero rows
            }

            $clean[] = ['account_id' => $accountId, 'debit' => $debit, 'credit' => $credit];
            $totalDebit += $debit;
            $totalCredit += $credit;
        }

        if (count($clean) < 2) {
            throw ValidationException::withMessages(['lines' => 'Jurnal minimal 2 baris (debit dan kredit).']);
        }
        if (round($totalDebit - $totalCredit, 2) !== 0.0) {
            throw ValidationException::withMessages([
                'lines' => 'Jurnal tidak seimbang: total debit (' . number_format($totalDebit, 2)
                    . ') harus sama dengan total kredit (' . number_format($totalCredit, 2) . ').',
            ]);
        }

        return $clean;
    }
}
