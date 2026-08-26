<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\JournalEntry;
use App\Services\JournalService;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * M12c — Friendly "Catat Transaksi" modes translate one amount into a correct,
 * balanced double-entry, and support proof attachments.
 */
class SimpleTransactionTest extends TestCase
{
    use RefreshDatabase;

    private JournalService $journals;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ChartOfAccountsSeeder::class);
        $this->journals = app(JournalService::class);
    }

    private function kas(): Account { return Account::where('code', '1000')->first(); }
    private function bank(): Account { return Account::where('code', '1100')->first(); }
    private function listrik(): Account { return Account::where('code', '5100')->first(); }
    private function jasa(): Account { return Account::where('code', '4000')->first(); }

    public function test_expense_debits_category_and_credits_cash(): void
    {
        $e = $this->journals->createSimple('expense', [
            'date' => '2026-08-24', 'description' => 'Listrik', 'amount' => 500000,
            'cash_account_id' => $this->kas()->id, 'category_account_id' => $this->listrik()->id,
        ]);

        $this->assertTrue($e->isBalanced());
        $this->assertSame(JournalEntry::KIND_EXPENSE, $e->kind);
        $debit = $e->lines->firstWhere(fn ($l) => $l->debit > 0);
        $credit = $e->lines->firstWhere(fn ($l) => $l->credit > 0);
        $this->assertSame($this->listrik()->id, $debit->account_id, 'beban di debit');
        $this->assertSame($this->kas()->id, $credit->account_id, 'kas di kredit');
        $this->assertSame(500000.0, (float) $debit->debit);
    }

    public function test_income_debits_cash_and_credits_category(): void
    {
        $e = $this->journals->createSimple('income', [
            'date' => '2026-08-24', 'description' => 'Jasa', 'amount' => 1000000,
            'cash_account_id' => $this->kas()->id, 'category_account_id' => $this->jasa()->id,
        ]);

        $debit = $e->lines->firstWhere(fn ($l) => $l->debit > 0);
        $credit = $e->lines->firstWhere(fn ($l) => $l->credit > 0);
        $this->assertSame($this->kas()->id, $debit->account_id, 'kas di debit');
        $this->assertSame($this->jasa()->id, $credit->account_id, 'pendapatan di kredit');
        $this->assertTrue($e->isBalanced());
    }

    public function test_transfer_debits_destination_and_credits_source(): void
    {
        $e = $this->journals->createSimple('transfer', [
            'date' => '2026-08-24', 'description' => 'Setor bank', 'amount' => 200000,
            'from_account_id' => $this->kas()->id, 'to_account_id' => $this->bank()->id,
        ]);

        $debit = $e->lines->firstWhere(fn ($l) => $l->debit > 0);
        $credit = $e->lines->firstWhere(fn ($l) => $l->credit > 0);
        $this->assertSame($this->bank()->id, $debit->account_id, 'tujuan di debit');
        $this->assertSame($this->kas()->id, $credit->account_id, 'asal di kredit');
    }

    public function test_transfer_to_same_account_is_rejected(): void
    {
        $this->expectException(ValidationException::class);
        $this->journals->createSimple('transfer', [
            'date' => '2026-08-24', 'description' => 'x', 'amount' => 100,
            'from_account_id' => $this->kas()->id, 'to_account_id' => $this->kas()->id,
        ]);
    }

    public function test_zero_amount_is_rejected(): void
    {
        $this->expectException(ValidationException::class);
        $this->journals->createSimple('expense', [
            'date' => '2026-08-24', 'description' => 'x', 'amount' => 0,
            'cash_account_id' => $this->kas()->id, 'category_account_id' => $this->listrik()->id,
        ]);
    }

    public function test_attachment_is_stored_and_removable(): void
    {
        Storage::fake('local');

        $e = $this->journals->createSimple('expense', [
            'date' => '2026-08-24', 'description' => 'Listrik', 'amount' => 500000,
            'cash_account_id' => $this->kas()->id, 'category_account_id' => $this->listrik()->id,
        ], UploadedFile::fake()->create('nota.pdf', 100, 'application/pdf'));

        $this->assertTrue($e->hasAttachment());
        Storage::disk('local')->assertExists($e->attachment_path);
        $this->assertSame('nota.pdf', $e->attachment_name);

        $this->journals->deleteAttachment($e);
        $this->assertFalse($e->fresh()->hasAttachment());
    }

    public function test_editing_simple_entry_updates_lines(): void
    {
        $e = $this->journals->createSimple('expense', [
            'date' => '2026-08-24', 'description' => 'Listrik', 'amount' => 500000,
            'cash_account_id' => $this->kas()->id, 'category_account_id' => $this->listrik()->id,
        ]);

        $this->journals->updateSimple($e, 'expense', [
            'date' => '2026-08-25', 'description' => 'Listrik revisi', 'amount' => 650000,
            'cash_account_id' => $this->bank()->id, 'category_account_id' => $this->listrik()->id,
        ]);

        $fresh = $e->fresh()->load('lines');
        $this->assertSame('Listrik revisi', $fresh->description);
        $this->assertSame(650000.0, $fresh->totalDebit());
        $credit = $fresh->lines->firstWhere(fn ($l) => $l->credit > 0);
        $this->assertSame($this->bank()->id, $credit->account_id, 'sumber dana pindah ke Bank');
    }
}
