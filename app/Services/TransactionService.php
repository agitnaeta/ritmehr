<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Loan;
use App\Models\LoanPayment;
use App\Models\SalaryRecap;
use App\Models\User;
use App\Services\Acc\AccTransaction;
use App\Services\Acc\AccTransactionType;
use App\Services\Acc\LedgerInterface;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class TransactionService
{

    protected  $acc;

    protected $active;
    public function __construct(LedgerInterface $acc) {
        $this->acc = $acc;
        // M12/M15: the internal ledger always records; the external Firefly
        // integration stays behind the acc_active toggle so nothing is sent
        // out until credentials are configured.
        $mode = setting('acc_mode', 'internal');
        $this->active = $mode === 'internal'
            ? true
            : (bool) setting('acc_active', env('ACC_ACTIVE'));
    }

    protected function newTransaction(): AccTransaction
    {
        return new AccTransaction();
    }

    /**
     * Posting rules per transaction code, expressed as account ROLES.
     *
     * Everything is now driven by the chart of accounts (/admin/account): each
     * account carries a role, and these rules say which role is the source
     * (credit) and which is the destination (debit). No separate mapping table.
     *
     * @return array{source: \App\Models\Account, destination: \App\Models\Account}|null
     */
    protected function resolveAccounts(string $code): ?array
    {
        // code => [source role, destination role]
        $rules = [
            'GAJIAN'      => [Account::ROLE_CASH, Account::ROLE_SALARY_EXPENSE],
            'KASBON'      => [Account::ROLE_CASH, Account::ROLE_LOAN_RECEIVABLE],
            'BAYARKASBON' => [Account::ROLE_LOAN_RECEIVABLE, Account::ROLE_CASH],
        ];

        if (! isset($rules[$code])) {
            return null;
        }

        [$sourceRole, $destRole] = $rules[$code];
        $source = Account::forRole($sourceRole);
        $destination = Account::forRole($destRole);

        if (! $source || ! $destination) {
            // Chart of accounts not fully configured — skip rather than crash.
            Log::warning("Akuntansi: akun untuk kode {$code} belum lengkap (source={$sourceRole}, dest={$destRole}).");
            return null;
        }

        return ['source' => $source, 'destination' => $destination];
    }

    public function recordSalaryToACC(SalaryRecap $data): void
    {
        if(!$this->active){
            return ;
        }
        $code = "GAJIAN";
        $accounts = $this->resolveAccounts($code);
        if (! $accounts) {
            return;
        }
        $user = User::find($data->user_id);
        $transaction = $this->newTransaction();
        $transaction->type = AccTransactionType::WITHDRAWAL;
        $transaction->amount = $data->received;
        $transaction->date = $data->updated_at;
        $transaction->description = "$code - $user->name";
        $transaction->source_id = $accounts['source']->id;
        $transaction->destination_id = $accounts['destination']->id;
        $transaction->tags = $code;
        $transaction->notes = $data->method;
        $transaction->internal_reference = "ABSEN-$code-".$data->id;
        $transaction->external_id =$data->id;

        // Save To ACC
        $record = $this->acc->withdraw($transaction);

        // save transaction id to database
        $data->acc_id = $record->data->id;
        $data->saveQuietly();
    }

    public function updateRecordSalaryToACC(SalaryRecap $data): void
    {
        if(!$this->active){
            return ;
        }
        if($data->acc_id == null){
            $this->recordSalaryToACC($data);
        }
        else{
            $code = "GAJIAN";
            $accounts = $this->resolveAccounts($code);
            if (! $accounts) {
                return;
            }
            $user = User::find($data->user_id);
            $transaction = $this->newTransaction();
            $transaction->type = AccTransactionType::WITHDRAWAL;
            $transaction->amount = $data->received;
            $transaction->date = $data->updated_at;
            $transaction->description = "$code - $user->name";
            $transaction->source_id = $accounts['source']->id;
            $transaction->destination_id = $accounts['destination']->id;
            $transaction->tags = $code;
            $transaction->notes = $data->method;
            $transaction->internal_reference = "ABSEN-$code-".$data->id;
            $transaction->external_id =$data->id;
            $this->acc->updateTransaction($data->acc_id,$transaction);
        }
    }

    public function deleteRecordSalaryToACC(SalaryRecap $data)
    {
        if(!$this->active){
            return ;
        }
        if($data->acc_id){
            $this->acc->delete($data->acc_id);
        }
    }


    public function recordPayLoanACC(LoanPayment $data): void
    {
        if(!$this->active){
            return ;
        }
        $code = "BAYARKASBON";
        $accounts = $this->resolveAccounts($code);
        if (! $accounts) {
            return;
        }
        $user = User::find($data->user_id);
        $transaction = $this->newTransaction();
        $transaction->type = AccTransactionType::DEPOSIT;
        $transaction->amount = $data->amount;
        $transaction->date = $data->date;
        $transaction->description = "$code - $user->name";
        $transaction->source_id = $accounts['source']->id;
        $transaction->destination_id = $accounts['destination']->id;
        $transaction->tags = $code;
        $transaction->notes = $code;
        $transaction->internal_reference = "ABSEN-$code-".$data->id;
        $transaction->external_id =$data->id;

        // Save To ACC
        $record = $this->acc->deposit($transaction);

        // save transaction id to database
        $data->acc_id = $record->data->id;
        $data->saveQuietly();
    }

    public function updateRecordPayLoanACC(LoanPayment $data): void
    {
        if(!$this->active){
            return ;
        }
        if($data->acc_id == null){
            $this->recordPayLoanACC($data);
        }

        else{
            $code = "BAYARKASBON";
            $accounts = $this->resolveAccounts($code);
            if (! $accounts) {
                return;
            }
            $user = User::find($data->user_id);

            $transaction = $this->newTransaction();
            $transaction->amount = $data->amount;
            $transaction->description = "$code - $user->name";
            $transaction->date = $data->date;
            $transaction->source_id = $accounts['source']->id;
            $transaction->destination_id = $accounts['destination']->id;

            $this->acc->updateTransaction($data->acc_id,$transaction);
        }

    }

    public function deleteRecordPayLoanAcc(LoanPayment $data)
    {
        if(!$this->active){
            return ;
        }
        if($data->acc_id){
            $this->acc->delete($data->acc_id);
        }
    }


    /**
     * Service For Loan
     */



    public function recordLoanACC(Loan $loan)
    {
        if(!$this->active){
            return ;
        }
        $code = "KASBON";
        $accounts = $this->resolveAccounts($code);
        if (! $accounts) {
            return;
        }
        $user = User::find($loan->user_id);

        $transaction = $this->newTransaction();
        $transaction->type = AccTransactionType::DEPOSIT;
        $transaction->amount = $loan->amount;
        $transaction->date = $loan->date;
        $transaction->description = "$code - $user->name";
        $transaction->source_id = $accounts['source']->id;
        $transaction->destination_id = $accounts['destination']->id;
        $transaction->tags = $code;
        $transaction->notes = $code;
        $transaction->internal_reference = "ABSEN-$code-".$loan->id;
        $transaction->external_id =$loan->id;
        $record = $this->acc->withdraw($transaction);

        // save transaction id to database
        $loan->acc_id = $record->data->id;
        $loan->saveQuietly();
    }

    public function updateRecordLoanACC(Loan $loan)
    {
        if(!$this->active){
            return ;
        }
        $code = "KASBON";
        $accounts = $this->resolveAccounts($code);
        if (! $accounts) {
            return;
        }

        if($loan->acc_id == null){
            $this->recordLoanACC($loan);
        }
        else{
            $user = User::find($loan->user_id);
            $transaction = $this->newTransaction();
            $transaction->amount = $loan->amount;
            $transaction->description = "$code - $user->name";
            $transaction->date = $loan->date;
            $transaction->source_id = $accounts['source']->id;
            $transaction->destination_id = $accounts['destination']->id;
            $this->acc->updateTransaction($loan->acc_id, $transaction);
        }
    }

    public function deleteRecordLoanACC(Loan $loan)
    {
        if(!$this->active){
            return ;
        }

        if($loan->acc_id){
            $this->acc->delete($loan->acc_id);
        }
    }




}
