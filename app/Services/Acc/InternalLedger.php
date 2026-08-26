<?php

namespace App\Services\Acc;

use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use Illuminate\Support\Facades\DB;

/**
 * M12 — Self-managed double-entry general ledger.
 *
 * Drop-in replacement for the Firefly III adapter (App\Services\Acc\Acc): same
 * method surface (LedgerInterface), but every "transaction" is a balanced
 * journal entry recorded locally — no external API call.
 *
 * Posting rule: value flows from source → destination, so we DEBIT the
 * destination account and CREDIT the source account. This is always balanced;
 * whether a debit increases or decreases a balance is decided by the account
 * type (see Account::balance()).
 */
class InternalLedger implements LedgerInterface
{
    public function withdraw(AccTransaction $data)
    {
        return $this->post($data);
    }

    public function deposit(AccTransaction $data)
    {
        return $this->post($data);
    }

    /**
     * Create (or, if the reference already exists, refresh) a balanced entry.
     * Idempotent on `internal_reference` so re-posting the same source record
     * updates rather than duplicates.
     */
    private function post(AccTransaction $data): object
    {
        return DB::transaction(function () use ($data) {
            $entry = JournalEntry::updateOrCreate(
                ['reference' => $data->internal_reference ?: null],
                [
                    'date'        => $this->normaliseDate($data->date),
                    'description' => $data->description,
                    'source'      => $this->sourceFromTags($data->tags ?? ''),
                    'external_id' => $data->external_id ?? null,
                ]
            );

            // Rebuild lines so an update reflects the new amount/accounts.
            $entry->lines()->delete();

            $amount = (float) $data->amount;

            JournalLine::create([
                'journal_entry_id' => $entry->id,
                'account_id'       => (int) $data->destination_id,
                'debit'            => $amount,
                'credit'           => 0,
            ]);
            JournalLine::create([
                'journal_entry_id' => $entry->id,
                'account_id'       => (int) $data->source_id,
                'debit'            => 0,
                'credit'           => $amount,
            ]);

            // Match Firefly's return shape: $record->data->id
            return (object) ['data' => (object) ['id' => $entry->id]];
        });
    }

    public function updateTransaction(string $id, AccTransaction $transaction)
    {
        $entry = JournalEntry::find($id);
        if (! $entry) {
            // Nothing to update — behave like a fresh post so data isn't lost.
            return $this->post($transaction);
        }

        return DB::transaction(function () use ($entry, $transaction) {
            $entry->update([
                'date'        => $this->normaliseDate($transaction->date),
                'description' => $transaction->description ?? $entry->description,
            ]);

            $entry->lines()->delete();
            $amount = (float) $transaction->amount;

            JournalLine::create([
                'journal_entry_id' => $entry->id,
                'account_id'       => (int) $transaction->destination_id,
                'debit'            => $amount,
                'credit'           => 0,
            ]);
            JournalLine::create([
                'journal_entry_id' => $entry->id,
                'account_id'       => (int) $transaction->source_id,
                'debit'            => 0,
                'credit'           => $amount,
            ]);

            return (object) ['data' => (object) ['id' => $entry->id]];
        });
    }

    public function delete(string $id)
    {
        $entry = JournalEntry::find($id);
        if ($entry) {
            $entry->delete(); // cascade removes lines
        }

        return (object) ['data' => (object) ['id' => $id]];
    }

    public function getAccounts(): array
    {
        return Account::where('is_active', true)
            ->orderBy('code')
            ->get()
            ->mapWithKeys(fn (Account $a) => [
                $a->id => "({$a->code}) {$a->name} - {$a->typeLabel()}",
            ])
            ->toArray();
    }

    private function normaliseDate($date): string
    {
        try {
            return \Carbon\Carbon::parse($date)->toDateString();
        } catch (\Throwable $e) {
            return now()->toDateString();
        }
    }

    /** Map the transaction tag/code to a ledger source category. */
    private function sourceFromTags(string $tags): string
    {
        return match (strtoupper($tags)) {
            'GAJIAN'      => 'salary',
            'KASBON'      => 'loan',
            'BAYARKASBON' => 'loan_payment',
            default       => 'manual',
        };
    }
}
