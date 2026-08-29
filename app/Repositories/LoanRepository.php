<?php

namespace App\Repositories;

use App\Models\Loan;
use App\Models\LoanPayment;
use App\Models\User;

class LoanRepository
{
    /**
     * Query builder rekap kasbon per-user (kasbon, terbayar sebagai subquery
     * alias). Dipakai bersama oleh recap() (Collection + selisih PHP) dan oleh
     * LoanExport (FromQuery + chunk) supaya definisi query tidak terduplikasi.
     */
    public static function recapQuery()
    {
        return User::select('id', 'name')
            ->selectSub(function ($query) {
                $query->selectRaw('COALESCE(SUM(amount),0)')
                    ->from('loans')
                    ->whereColumn('user_id', 'users.id');
            }, 'kasbon')
            ->selectSub(function ($query) {
                $query->selectRaw('COALESCE(SUM(amount),0)')
                    ->from('loan_payments')
                    ->whereColumn('user_id', 'users.id');
            }, 'terbayar');
    }

    public static function recap()
    {
        $recap = self::recapQuery()->get();

        // selisih dihitung dari alias yang sudah diambil (tanpa subquery korelasi ke-3)
        $recap->each(fn ($r) => $r->selisih = (int) $r->kasbon - (int) $r->terbayar);

        return $recap;
    }

    public static function detail(User $user){
        $loan = Loan::where('user_id',$user->id)->get();
        $loanPayment = LoanPayment::where('user_id',$user->id)->get();
        $total = $loan->sum('amount') - $loanPayment->sum('amount');
        return compact('loan','loanPayment','total');
    }
}
