<?php

namespace App\Services;

use App\Models\LoanPayment;
use App\Models\NationalHoliday;
use App\Models\Presence;
use App\Models\Salary;
use App\Models\SalaryRecap;
use App\Models\User;
use Carbon\Carbon;
use Database\Factories\TranslateFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SalaryService
{

    protected $presenceService;


    protected $transactionService;

    protected $taxService;

    public function __construct(PresenceService $presenceService = null, TransactionService $transactionService = null, TaxService $taxService = null)
    {
       $this->presenceService = $presenceService ?? app(PresenceService::class);
       $this->transactionService = $transactionService ?? app(TransactionService::class);
       $this->taxService = $taxService ?? app(TaxService::class);
    }

    public function recap(Presence $presence){

        $salaryRecap  = $this->getSalaryRecapRecords($presence);
        if(!$salaryRecap){
            $this->createSalaryRecap($presence);
        }else{
            $this->calculateSalaryRecap($salaryRecap);
        }
    }

    protected function getSalaryRecapRecords(Presence $presence){
        $user = User::find($presence->user_id);
        if($user==null){
            return false;
        }
        $salaryRecap = SalaryRecap::where('user_id',$user->id)
                                  ->where('recap_month',$this->recapMonth($presence))
                                  ->first();
        return $salaryRecap ?? false ;
    }
    private function recapMonth(Presence $presence){
        $recapMonth = Carbon::parse($presence->in);
        return $recapMonth->format('m-Y');
    }

    public function createSalaryRecap(Presence $presence){
        $salaryRecap = new SalaryRecap;
        $salaryRecap->user_id = $presence->user_id;
        $salaryRecap->recap_month = $this->recapMonth($presence);
        $salaryRecap->work_day = 0;
        $salaryRecap->late_day = 0;
        $salaryRecap->salary_amount = 0;
        $salaryRecap->overtime_amount = 0;
        $salaryRecap->loan_cut = 0;
        $salaryRecap->late_cut = 0;
        $salaryRecap->abstain_cut = 0;
        $salaryRecap->abstain_count = 0;
        $salaryRecap->received = 0;
        $salaryRecap->save();
    }

    public function calculateSalaryRecap(SalaryRecap $salaryRecap){
        $presence = $this->getPresenceRecords($salaryRecap);
        $user = User::with(['salary','schedule'])->find($salaryRecap->user_id);
        if($user === null || $user->salary === null){
            return $salaryRecap;
        }
        $salary = $user->salary;

        DB::transaction(function () use ($salaryRecap, $presence, $salary, $user) {
            $salaryRecap->work_day = $presence->count();
            $salaryRecap->late_day = $presence->sum('is_late');
            $salaryRecap->salary_amount = $salary->amount;
            $salaryRecap->overtime_amount = $presence->sum('is_overtime') * $salary->overtime_amount;
            $salaryRecap->abstain_cut = $this->unpaidLeaveDeduction($salaryRecap,$salary);
            $salaryRecap->abstain_count = $this->getAbstain($salaryRecap);
            $salaryRecap->late_minute_count = $presence->sum('late_minute');
            $salaryRecap->late_cut = $this->deductSalaryByLate($salaryRecap, $user);
            $salaryRecap->extra_time = $presence->sum('extra_time');
            $salaryRecap->extra_time_amount = $this->calculateExtraTimeAmount($salaryRecap, $user);
            $salaryRecap->received = $salaryRecap->salary_amount +
                $salaryRecap->overtime_amount -
                $salaryRecap->loan_cut -
                $salaryRecap->abstain_cut -
                $salaryRecap->late_cut + $salaryRecap->extra_time_amount;

            $salaryRecap->saveQuietly();

            // M05: hitung PPh21/BPJS/net_income otomatis tiap rekap dihitung
            // ulang, seharga gross yang baru saja final. applyToRecap menulis
            // dengan saveQuietly sehingga tidak memicu observer lagi.
            $this->taxService->applyToRecap($salaryRecap);

            // only update when there's a payment
            // avoid Issue always recall
            if($salaryRecap->paid){
                $this->transactionService->updateRecordSalaryToACC($salaryRecap);
            }
        });
    }

    /**
     * Deduction for days not worked and not covered by paid leave.
     *
     * Unpaid leave is deducted like an absence (that is what "unpaid" means),
     * but paid leave must not be — before leave management existed every
     * non-present day was charged here, which silently docked people for
     * approved holidays and sick days.
     */
    public function unpaidLeaveDeduction(SalaryRecap $salaryRecap, Salary $salary){
        $deductibleDays = $this->deductibleAbsenceDays($salaryRecap);

        return $deductibleDays * $salary->unpaid_leave_deduction;
    }

    /**
     * Days treated as unexcused absence — used for the abstain_count shown on
     * the recap. Paid *and* unpaid leave are excluded here because neither is
     * an unexplained absence; unpaid leave is charged separately.
     */
    public function getAbstain(SalaryRecap $salaryRecap){
        $available = $this->availableWorkDays($salaryRecap);
        $leave = $this->leaveDays($salaryRecap);

        return max(0, $available - $salaryRecap->work_day - $leave['total']);
    }

    /**
     * Unexcused absences plus unpaid leave — the days that actually cost money.
     */
    public function deductibleAbsenceDays(SalaryRecap $salaryRecap): int
    {
        $available = $this->availableWorkDays($salaryRecap);
        $leave = $this->leaveDays($salaryRecap);

        $unexcused = max(0, $available - $salaryRecap->work_day - $leave['total']);

        return $unexcused + $leave['unpaid'];
    }

    /**
     * Workdays in the month after removing scheduled off days and national
     * holidays.
     */
    public function availableWorkDays(SalaryRecap $salaryRecap): int
    {
        return $this->workdayInAMonth($salaryRecap) - $this->countOfNationalHoliday($salaryRecap);
    }

    /**
     * Approved leave in the recap month, split paid/unpaid.
     *
     * @return array{paid: int, unpaid: int, total: int}
     */
    public function leaveDays(SalaryRecap $salaryRecap): array
    {
        return app(LeaveService::class)->approvedLeaveDaysForRecap($salaryRecap);
    }

    public function offDayInMonth(SalaryRecap $salaryRecap){
        $month = $this->getRecapMonthCarbon($salaryRecap);
        $user = User::with('schedule')->find($salaryRecap->user_id);
        $useOffDays = $this->presenceService->useOffDays($user);
        $startMonth = $month->copy()->startOfMonth();
        $endMonth = $month->copy()->endOfMonth();
        $countOffDay = collect();

        for ($currentDay = $startMonth; $currentDay->lte($endMonth); $currentDay->addDay()) {
            $currentDay->locale('id_ID')->isoFormat('dddd');

            if ($useOffDays->contains(Str::lower($currentDay->dayName))) {
                $countOffDay->push($currentDay->dayName);
            }
        }

        return $countOffDay->count();
    }

    public function workdayInAMonth(SalaryRecap $salaryRecap){
        $month = $this->getRecapMonthCarbon($salaryRecap);
        return $month->daysInMonth - $this->offDayInMonth($salaryRecap);
    }

    public function getPresenceRecords(SalaryRecap $salaryRecap){
        $time = $this->getRecapMonthCarbon($salaryRecap);
        return  Presence::where('user_id',$salaryRecap->user_id)
            ->whereYear('in',$time->format('Y'))
            ->whereMonth('in',$time->format('m'))
            ->get();
    }

    public function getRecapMonthCarbon(SalaryRecap $salaryRecap)
    {
        return Carbon::createFromFormat('m-Y',$salaryRecap->recap_month);
    }

    public function countOfNationalHoliday(SalaryRecap$salaryRecap){
        $time = $this->getRecapMonthCarbon($salaryRecap);
        $nationalHoliday = NationalHoliday::whereMonth('date',$time->month)
            ->whereYear('date',$time->year)->get();
        return $nationalHoliday->count();
    }


    function payLoan(SalaryRecap $salaryRecap){
        if($salaryRecap->loan_cut > 0){
            // Check if a LoanPayment with the given salary_recap_id already exists
            $existingLoanPayment = LoanPayment::where('salary_recap_id', $salaryRecap->id)->first();

            if (!$existingLoanPayment) {
                // If no existing LoanPayment, create and save a new one
                $loanPayment = new LoanPayment();

                $loanPayment->user_id = $salaryRecap->user_id;
                $loanPayment->salary_recap_id = $salaryRecap->id;
                $loanPayment->amount = $salaryRecap->loan_cut;
                $loanPayment->date = $salaryRecap->updated_at;

                $loanPayment->save();

                // only update when there's a payment
                // avoid Issue always recall
                if($salaryRecap->paid){
                    $this->transactionService->updateRecordPayLoanACC($loanPayment);
                }
            } else {
                $existingLoanPayment->update([
                    'user_id' => $salaryRecap->user_id,
                    'amount' => $salaryRecap->loan_cut,
                    'date' => $salaryRecap->updated_at,
                ]);

                // only update when there's a payment
                // avoid Issue always recall
                if($salaryRecap->paid){
                    $this->transactionService->updateRecordPayLoanACC($existingLoanPayment);
                }
            }
        }
        else{
            $loanPayment = LoanPayment::where("salary_recap_id",$salaryRecap->id)
                ->first();
            if($loanPayment){
                $this->transactionService->deleteRecordPayLoanAcc($loanPayment);
                $loanPayment->delete();
            }
        }
    }

    public function removeLoanPayment(SalaryRecap $salaryRecap){
            $loan = LoanPayment::where('salary_recap_id',$salaryRecap->id)
                ->first();

            if(!$loan){
                return;
            }

            $this->transactionService->deleteRecordPayLoanAcc($loan);
            $loan->delete();
    }

    public function deductSalaryByLate(SalaryRecap $salaryRecap, User $user = null){
        if($user === null){
            $user = User::with('salary')->find($salaryRecap->user_id);
        }
        if($user->salary->type  === TranslateFactory::MINUTE){
            return $user->salary->fine_per_minute * $salaryRecap->late_minute_count;
        }
        else
        {
            return $user->salary->fine * $salaryRecap->late_day;
        }
    }
    public function calculateExtraTimeAmount($salaryRecap, User $user = null){
        // Extra time x salary extra time
        if($user === null){
            $user = User::with('salary')->find($salaryRecap->user_id);
        }
        if($user->salary->extra_time_rule == 1){
            return $user->salary->extra_time * $salaryRecap->extra_time;
        }
        return 0;
    }

    public function deleteWhenUncheck(SalaryRecap $salaryRecap)
    {
        // check if salary recap have acc id
        if($salaryRecap->acc_id && $salaryRecap->paid == 0 )
        {
            // Delete loan payment related
            $loanPayment = LoanPayment::where("salary_recap_id",$salaryRecap->id)
                                      ->first();
            if($loanPayment){
                $this->transactionService->deleteRecordPayLoanAcc($loanPayment);
                $loanPayment->delete();
            }


            // Delete salary recap
            $this->transactionService->deleteRecordSalaryToACC($salaryRecap);

            $salaryRecap->acc_id= NULL;
            $salaryRecap->saveQuietly();

        }
    }




}
