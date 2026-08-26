<?php

namespace App\Http\Controllers\Admin;

use App\Models\Account;
use App\Models\JournalEntry;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * M12 — Read views over the internal ledger: journal, general ledger per
 * account, and a trial balance. Postings themselves are created automatically
 * by TransactionService (payroll / loans), not typed here.
 */
class LedgerController extends Controller
{
    private function guard(): void
    {
        abort_unless(backpack_user()?->can('accounting.view'), 403, 'Anda tidak berhak melihat akuntansi.');
    }

    /** Journal — all entries, filterable by date range & account. */
    public function journal(Request $request)
    {
        $this->guard();

        $query = JournalEntry::with('lines.account')->orderByDesc('date')->orderByDesc('id');

        if ($from = $request->input('from')) {
            $query->whereDate('date', '>=', $from);
        }
        if ($to = $request->input('to')) {
            $query->whereDate('date', '<=', $to);
        }
        if ($accountId = $request->input('account_id')) {
            $query->whereHas('lines', fn ($q) => $q->where('account_id', $accountId));
        }

        return view('admin.accounting.journal', [
            'entries'  => $query->paginate(30)->withQueryString(),
            'accounts' => Account::orderBy('code')->get(),
            'filters'  => $request->only(['from', 'to', 'account_id']),
        ]);
    }

    /** General ledger — running balance for a single account. */
    public function ledger(Request $request)
    {
        $this->guard();

        $accounts = Account::orderBy('code')->get();
        $accountId = $request->input('account_id', optional($accounts->first())->id);
        $account = $accounts->firstWhere('id', (int) $accountId);

        $rows = [];
        $running = 0.0;

        if ($account) {
            $isDebitNormal = in_array($account->type, [Account::TYPE_ASSET, Account::TYPE_EXPENSE], true);

            $lines = $account->lines()
                ->with('entry')
                ->get()
                ->sortBy(fn ($l) => [optional($l->entry)->date, $l->id])
                ->values();

            foreach ($lines as $line) {
                $delta = $isDebitNormal
                    ? ($line->debit - $line->credit)
                    : ($line->credit - $line->debit);
                $running += $delta;

                $rows[] = [
                    'date'        => optional($line->entry)->date,
                    'description' => optional($line->entry)->description,
                    'debit'       => (float) $line->debit,
                    'credit'      => (float) $line->credit,
                    'balance'     => $running,
                ];
            }
        }

        return view('admin.accounting.ledger', [
            'accounts'  => $accounts,
            'account'   => $account,
            'rows'      => $rows,
            'endBalance' => $running,
        ]);
    }

    /** Trial balance — every account's debit/credit totals. */
    public function trialBalance()
    {
        $this->guard();

        $accounts = Account::orderBy('code')->get();
        $rows = [];
        $totalDebit = 0.0;
        $totalCredit = 0.0;

        foreach ($accounts as $account) {
            $debit = (float) $account->lines()->sum('debit');
            $credit = (float) $account->lines()->sum('credit');
            if ($debit == 0.0 && $credit == 0.0) {
                continue;
            }
            $rows[] = [
                'code'   => $account->code,
                'name'   => $account->name,
                'debit'  => $debit,
                'credit' => $credit,
            ];
            $totalDebit += $debit;
            $totalCredit += $credit;
        }

        return view('admin.accounting.trial_balance', [
            'rows'        => $rows,
            'totalDebit'  => $totalDebit,
            'totalCredit' => $totalCredit,
        ]);
    }

    /** Laba Rugi (Income Statement) — pendapatan − beban, opsional per rentang tanggal. */
    public function incomeStatement(Request $request)
    {
        $this->guard();

        $from = $request->input('from');
        $to = $request->input('to');

        $income = $this->accountRows(Account::TYPE_INCOME, $from, $to);
        $expense = $this->accountRows(Account::TYPE_EXPENSE, $from, $to);

        $totalIncome = array_sum(array_column($income, 'amount'));
        $totalExpense = array_sum(array_column($expense, 'amount'));

        return view('admin.accounting.income_statement', [
            'income'       => $income,
            'expense'      => $expense,
            'totalIncome'  => $totalIncome,
            'totalExpense' => $totalExpense,
            'netProfit'    => $totalIncome - $totalExpense,
            'filters'      => ['from' => $from, 'to' => $to],
        ]);
    }

    /** Neraca (Balance Sheet) — Aset = Kewajiban + Ekuitas (+ laba berjalan). */
    public function balanceSheet()
    {
        $this->guard();

        $assets = $this->accountRows(Account::TYPE_ASSET);
        $liabilities = $this->accountRows(Account::TYPE_LIABILITY);
        $equity = $this->accountRows(Account::TYPE_EQUITY);

        $totalAssets = array_sum(array_column($assets, 'amount'));
        $totalLiabilities = array_sum(array_column($liabilities, 'amount'));
        $totalEquity = array_sum(array_column($equity, 'amount'));

        // Laba berjalan (income − expense) masuk ke ekuitas agar neraca seimbang.
        $income = array_sum(array_column($this->accountRows(Account::TYPE_INCOME), 'amount'));
        $expense = array_sum(array_column($this->accountRows(Account::TYPE_EXPENSE), 'amount'));
        $retainedEarnings = $income - $expense;

        $totalEquityWithEarnings = $totalEquity + $retainedEarnings;

        return view('admin.accounting.balance_sheet', [
            'assets'                  => $assets,
            'liabilities'             => $liabilities,
            'equity'                  => $equity,
            'totalAssets'             => $totalAssets,
            'totalLiabilities'        => $totalLiabilities,
            'totalEquity'             => $totalEquity,
            'retainedEarnings'        => $retainedEarnings,
            'totalEquityWithEarnings' => $totalEquityWithEarnings,
            'totalLiabEquity'         => $totalLiabilities + $totalEquityWithEarnings,
        ]);
    }

    /**
     * Per-account balances for a given account type, in the account's natural
     * (positive) direction. Optional date range for income/expense.
     *
     * @return array<int, array{code:string, name:string, amount:float}>
     */
    private function accountRows(string $type, ?string $from = null, ?string $to = null): array
    {
        $isDebitNormal = in_array($type, [Account::TYPE_ASSET, Account::TYPE_EXPENSE], true);
        $rows = [];

        foreach (Account::where('type', $type)->orderBy('code')->get() as $account) {
            $q = $account->lines();
            if ($from || $to) {
                $q = $q->whereHas('entry', function ($e) use ($from, $to) {
                    if ($from) $e->whereDate('date', '>=', $from);
                    if ($to) $e->whereDate('date', '<=', $to);
                });
            }
            $debit = (float) $q->sum('debit');
            $credit = (float) $q->sum('credit');
            $amount = $isDebitNormal ? ($debit - $credit) : ($credit - $debit);

            if (round($amount, 2) == 0.0) {
                continue;
            }
            $rows[] = ['code' => $account->code, 'name' => $account->name, 'amount' => $amount];
        }

        return $rows;
    }
}
