<?php

namespace App\Services;

use App\Models\Loan;
use App\Models\LoanPayment;
use App\Models\SalaryRecap;
use App\Models\User;
use App\Services\Acc\Acc;
use App\Services\Acc\AccTransaction;
use App\Services\Acc\AccTransactionType;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class TransactionService
{

    protected  $acc;

    protected $active;
    public function __construct(Acc $acc) {
        $this->acc = $acc;
        $this->active = env('ACC_ACTIVE');
    }

    protected function newTransaction(): AccTransaction
    {
        return new AccTransaction();
    }

    public function recordSalaryToACC(SalaryRecap $data): void
    {
        if(!$this->active){
            return ;
        }
        $code = "GAJIAN";
        $user = User::find($data->user_id);
        $acc  = \App\Models\Acc::where("code",$code)->first();
        $transaction = $this->newTransaction();
        $transaction->type = AccTransactionType::WITHDRAWAL;
        $transaction->amount = $data->received;
        $transaction->date = $data->updated_at;
        $transaction->description = "$code - $user->name";
        $transaction->source_id = $acc->source_id;
        $transaction->destination_id = $acc->destination_id;
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
            $user = User::find($data->user_id);
            $acc  = \App\Models\Acc::where("code",$code)->first();
            $transaction = $this->newTransaction();
            $transaction->type = AccTransactionType::WITHDRAWAL;
            $transaction->amount = $data->received;
            $transaction->date = $data->updated_at;
            $transaction->description = "$code - $user->name";
            $transaction->source_id = $acc->source_id;
            $transaction->destination_id = $acc->destination_id;
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
        $user = User::find($data->user_id);
        $acc  = \App\Models\Acc::where("code",$code)->first();
        $transaction = $this->newTransaction();
        $transaction->type = AccTransactionType::DEPOSIT;
        $transaction->amount = $data->amount;
        $transaction->date = $data->date;
        $transaction->description = "$code - $user->name";
        $transaction->source_id = $acc->source_id;
        $transaction->destination_id = $acc->destination_id;
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
            $user = User::find($data->user_id);
            $acc  = \App\Models\Acc::where("code",$code)->first();

            $transaction = $this->newTransaction();
            $transaction->amount = $data->amount;
            $transaction->description = "$code - $user->name";
            $transaction->date = $data->date;
            $transaction->source_id = $acc->source_id;
            $transaction->destination_id = $acc->destination_id;

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
        $user = User::find($loan->user_id);
        $acc  = \App\Models\Acc::where("code",$code)->first();

        $transaction = $this->newTransaction();
        $transaction->type = AccTransactionType::DEPOSIT;
        $transaction->amount = $loan->amount;
        $transaction->date = $loan->date;
        $transaction->description = "$code - $user->name";
        $transaction->source_id = $acc->source_id;
        $transaction->destination_id = $acc->destination_id;
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
        $user = User::find($loan->user_id);
        $acc  = \App\Models\Acc::where("code",$code)->first();

        if($loan->acc_id == null){
            $this->recordLoanACC($loan);
        }
        else{
            $transaction = $this->newTransaction();
            $transaction->amount = $loan->amount;
            $transaction->description = "$code - $user->name";
            $transaction->date = $loan->date;
            $transaction->source_id = $acc->source_id;
            $transaction->destination_id = $acc->destination_id;
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
