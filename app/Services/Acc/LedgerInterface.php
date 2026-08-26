<?php

namespace App\Services\Acc;

/**
 * M12 — Contract for a ledger backend.
 *
 * Both the internal double-entry ledger and the legacy Firefly III adapter
 * implement this, so TransactionService is agnostic to which one is active
 * (selected via the `acc_mode` platform setting).
 *
 * withdraw()/deposit() must return an object shaped like `->data->id` so the
 * caller can persist the resulting transaction id as `acc_id`.
 */
interface LedgerInterface
{
    public function withdraw(AccTransaction $data);

    public function deposit(AccTransaction $data);

    public function updateTransaction(string $id, AccTransaction $transaction);

    public function delete(string $id);

    /**
     * Accounts available for the source/destination mapping UI.
     *
     * @return array<int|string, string> keyed by account id → human label
     */
    public function getAccounts(): array;
}
