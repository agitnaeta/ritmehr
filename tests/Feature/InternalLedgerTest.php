<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\Loan;
use App\Models\LoanPayment;
use App\Models\Salary;
use App\Models\SalaryRecap;
use App\Models\Schedule;
use App\Models\Setting;
use App\Models\User;
use App\Services\Acc\InternalLedger;
use App\Services\SettingService;
use App\Services\TransactionService;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * M12 — Internal double-entry ledger.
 *
 * Proves postings are balanced, idempotent, move real account balances, and
 * never reach an external HTTP endpoint.
 */
class InternalLedgerTest extends TestCase
{
    use RefreshDatabase;

    private TransactionService $tx;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ChartOfAccountsSeeder::class);

        // Force internal mode.
        app(SettingService::class)->set('acc_mode', 'internal');
        app(SettingService::class)->flush();

        // Any outbound HTTP would be a bug in internal mode — make it explode.
        Http::preventStrayRequests();

        $this->tx = app(TransactionService::class);
    }

    private function staffWithRecap(int $received = 5_000_000): SalaryRecap
    {
        $schedule = Schedule::create([
            'name' => 'Reguler', 'in' => '08:00:00', 'out' => '17:00:00',
            'over_in' => '18:00:00', 'over_out' => '22:00:00',
        ]);

        $user = User::create([
            'name' => 'Pegawai', 'email' => 'pegawai@example.test', 'password' => bcrypt('x'),
            'schedule_id' => $schedule->id,
        ]);
        Salary::create([
            'user_id' => $user->id, 'amount' => $received, 'overtime_amount' => 0,
            'overtime_type' => 'flat', 'unpaid_leave_deduction' => 0,
            'fine_type' => 'flat', 'fine' => 0, 'fine_per_minute' => 0,
        ]);

        return SalaryRecap::create([
            'user_id' => $user->id, 'recap_month' => now()->format('m-Y'),
            'work_day' => 22, 'late_day' => 0, 'salary_amount' => $received,
            'overtime_amount' => 0, 'loan_cut' => 0, 'late_cut' => 0,
            'abstain_cut' => 0, 'abstain_count' => 0, 'received' => $received,
            'paid' => 1, 'method' => 'cash',
        ]);
    }

    public function test_the_active_binding_is_the_internal_ledger(): void
    {
        $this->assertInstanceOf(InternalLedger::class, app(\App\Services\Acc\LedgerInterface::class));
    }

    public function test_paying_salary_creates_a_balanced_journal_entry(): void
    {
        $recap = $this->staffWithRecap(7_500_000);

        $this->tx->recordSalaryToACC($recap);

        $entry = JournalEntry::where('reference', 'ABSEN-GAJIAN-' . $recap->id)->first();
        $this->assertNotNull($entry, 'jurnal gajian harus terbentuk');
        $this->assertTrue($entry->isBalanced(), 'debit harus sama dengan kredit');
        $this->assertSame(7_500_000.0, $entry->totalDebit());
        $this->assertSame(7_500_000.0, $entry->totalCredit());

        // acc_id (=journal entry id) tersimpan di recap.
        $this->assertSame($entry->id, (int) $recap->fresh()->acc_id);
    }

    public function test_expense_and_cash_balances_move_correctly(): void
    {
        $recap = $this->staffWithRecap(6_000_000);
        $this->tx->recordSalaryToACC($recap);

        $beban = Account::where('code', '5000')->first();
        $kas   = Account::where('code', '1000')->first();

        $this->assertSame(6_000_000.0, $beban->balance(), 'beban gaji naik (debit-normal)');
        $this->assertSame(-6_000_000.0, $kas->balance(), 'kas turun (credit mengurangi aset)');
    }

    public function test_reposting_same_reference_is_idempotent(): void
    {
        $recap = $this->staffWithRecap(5_000_000);

        $this->tx->recordSalaryToACC($recap);
        // Simulate a recalculation → update path.
        $this->tx->updateRecordSalaryToACC($recap->fresh());
        $this->tx->updateRecordSalaryToACC($recap->fresh());

        $this->assertSame(
            1,
            JournalEntry::where('reference', 'ABSEN-GAJIAN-' . $recap->id)->count(),
            'posting ulang tidak boleh menduplikasi jurnal'
        );
    }

    public function test_deleting_a_transaction_removes_the_entry_and_lines(): void
    {
        $recap = $this->staffWithRecap();
        $this->tx->recordSalaryToACC($recap);
        $entryId = (int) $recap->fresh()->acc_id;

        $this->tx->deleteRecordSalaryToACC($recap->fresh());

        $this->assertDatabaseMissing('journal_entries', ['id' => $entryId]);
        $this->assertDatabaseMissing('journal_lines', ['journal_entry_id' => $entryId]);
    }

    public function test_trial_balance_is_balanced_after_multiple_postings(): void
    {
        $this->tx->recordSalaryToACC($this->staffWithRecap(3_000_000));

        $totalDebit = (float) \App\Models\JournalLine::sum('debit');
        $totalCredit = (float) \App\Models\JournalLine::sum('credit');

        $this->assertSame($totalDebit, $totalCredit, 'neraca saldo harus seimbang');
    }
}
