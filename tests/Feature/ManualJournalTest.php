<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\Salary;
use App\Models\SalaryRecap;
use App\Models\Schedule;
use App\Models\User;
use App\Services\JournalService;
use App\Services\TransactionService;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * M12 — Manual journals, locking of auto entries, and reversals.
 */
class ManualJournalTest extends TestCase
{
    use RefreshDatabase;

    private JournalService $journals;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ChartOfAccountsSeeder::class);
        app(\App\Services\SettingService::class)->set('acc_mode', 'internal');
        app(\App\Services\SettingService::class)->flush();
        $this->journals = app(JournalService::class);
    }

    private function cash(): Account { return Account::forRole(Account::ROLE_CASH); }
    private function expense(): Account { return Account::where('code', '5000')->first(); }

    public function test_manual_journal_can_be_created_when_balanced(): void
    {
        $entry = $this->journals->createManual('2026-08-24', 'Bayar listrik', [
            ['account_id' => $this->expense()->id, 'debit' => 500000, 'credit' => 0],
            ['account_id' => $this->cash()->id,    'debit' => 0,      'credit' => 500000],
        ]);

        $this->assertTrue($entry->isManual());
        $this->assertTrue($entry->isBalanced());
        $this->assertSame(2, $entry->lines()->count());
    }

    public function test_unbalanced_manual_journal_is_rejected(): void
    {
        $this->expectException(ValidationException::class);
        $this->journals->createManual('2026-08-24', 'Pincang', [
            ['account_id' => $this->expense()->id, 'debit' => 100, 'credit' => 0],
            ['account_id' => $this->cash()->id,    'debit' => 0,   'credit' => 50],
        ]);
    }

    public function test_single_line_journal_is_rejected(): void
    {
        $this->expectException(ValidationException::class);
        $this->journals->createManual('2026-08-24', 'Sebelah', [
            ['account_id' => $this->cash()->id, 'debit' => 100, 'credit' => 0],
        ]);
    }

    public function test_manual_journal_can_be_edited_and_deleted(): void
    {
        $entry = $this->journals->createManual('2026-08-24', 'Awal', [
            ['account_id' => $this->expense()->id, 'debit' => 200000, 'credit' => 0],
            ['account_id' => $this->cash()->id,    'debit' => 0,      'credit' => 200000],
        ]);

        $this->journals->updateManual($entry, '2026-08-25', 'Revisi', [
            ['account_id' => $this->expense()->id, 'debit' => 300000, 'credit' => 0],
            ['account_id' => $this->cash()->id,    'debit' => 0,      'credit' => 300000],
        ]);
        $this->assertSame('Revisi', $entry->fresh()->description);
        $this->assertSame(300000.0, $entry->fresh()->totalDebit());

        $this->journals->deleteManual($entry);
        $this->assertDatabaseMissing('journal_entries', ['id' => $entry->id]);
    }

    public function test_auto_posted_entry_cannot_be_edited_or_deleted(): void
    {
        $recap = $this->postSalary();
        $auto = JournalEntry::where('source', 'salary')->firstOrFail();

        $this->assertTrue($auto->isLocked());

        try {
            $this->journals->updateManual($auto, '2026-08-24', 'hack', []);
            $this->fail('auto entry should not be editable');
        } catch (ValidationException $e) {
            $this->assertTrue(true);
        }

        try {
            $this->journals->deleteManual($auto);
            $this->fail('auto entry should not be deletable');
        } catch (ValidationException $e) {
            $this->assertTrue(true);
        }
    }

    public function test_auto_entry_can_be_corrected_via_reversal(): void
    {
        $this->postSalary();
        $auto = JournalEntry::where('source', 'salary')->firstOrFail();

        $reversal = $this->journals->reverse($auto);

        $this->assertSame($auto->id, $reversal->reversed_entry_id);
        $this->assertTrue($reversal->isBalanced());
        // Debits and credits are mirrored.
        $this->assertSame($auto->totalDebit(), $reversal->totalCredit());
        $this->assertSame($auto->totalCredit(), $reversal->totalDebit());
        $this->assertTrue($auto->fresh()->isReversed());
    }

    public function test_an_entry_cannot_be_reversed_twice(): void
    {
        $this->postSalary();
        $auto = JournalEntry::where('source', 'salary')->firstOrFail();
        $this->journals->reverse($auto);

        $this->expectException(ValidationException::class);
        $this->journals->reverse($auto);
    }

    private function postSalary(): SalaryRecap
    {
        $schedule = Schedule::create([
            'name' => 'R', 'in' => '08:00:00', 'out' => '17:00:00',
            'over_in' => '18:00:00', 'over_out' => '22:00:00',
        ]);
        $user = User::create([
            'name' => 'P', 'email' => 'p@example.test', 'password' => bcrypt('x'),
            'schedule_id' => $schedule->id,
        ]);
        Salary::create([
            'user_id' => $user->id, 'amount' => 5_000_000, 'overtime_amount' => 0,
            'overtime_type' => 'flat', 'unpaid_leave_deduction' => 0,
            'fine_type' => 'flat', 'fine' => 0, 'fine_per_minute' => 0,
        ]);
        $recap = SalaryRecap::create([
            'user_id' => $user->id, 'recap_month' => now()->format('m-Y'),
            'work_day' => 22, 'late_day' => 0, 'salary_amount' => 5_000_000,
            'overtime_amount' => 0, 'loan_cut' => 0, 'late_cut' => 0,
            'abstain_cut' => 0, 'abstain_count' => 0, 'received' => 5_000_000,
            'paid' => 1, 'method' => 'cash',
        ]);
        app(TransactionService::class)->recordSalaryToACC($recap);

        return $recap;
    }
}
