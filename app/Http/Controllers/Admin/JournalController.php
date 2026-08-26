<?php

namespace App\Http\Controllers\Admin;

use App\Models\Account;
use App\Models\JournalEntry;
use App\Services\JournalService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Prologue\Alerts\Facades\Alert;

/**
 * M12 + M12c — Journal management.
 *
 * "Catat Transaksi": friendly modes (expense/income/transfer) for non-accountants
 * where they enter one amount and pick accounts by purpose; the double-entry is
 * built behind the scenes. An "advanced" mode keeps the raw debit/credit form.
 *
 * Auto-posted entries stay locked; correct via reversal.
 */
class JournalController extends Controller
{
    public function __construct(private readonly JournalService $journals)
    {
    }

    private function guardEdit(): void
    {
        abort_unless(backpack_user()?->can('accounting.edit'), 403, 'Anda tidak berhak mengelola jurnal.');
    }

    /** Step 1 — choose what kind of transaction to record. */
    public function chooser()
    {
        $this->guardEdit();
        return view('admin.accounting.transaction_chooser');
    }

    /** Step 2 — the form for a given simple kind (or advanced). */
    public function create(Request $request)
    {
        $this->guardEdit();
        $kind = $request->query('kind', JournalEntry::KIND_EXPENSE);

        if ($kind === JournalEntry::KIND_GENERAL) {
            return view('admin.accounting.journal_form', [
                'entry'        => null,
                'accounts'     => Account::active()->orderBy('code')->get(),
                'accountsJson' => Account::active()->orderBy('code')->get()
                    ->map(fn ($a) => ['id' => $a->id, 'label' => "({$a->code}) {$a->name}"])->values(),
                'presetLines'  => collect(),
            ]);
        }

        return view('admin.accounting.transaction_form', $this->simpleFormData($kind, null));
    }

    public function store(Request $request)
    {
        $this->guardEdit();
        $kind = $request->input('kind', JournalEntry::KIND_EXPENSE);

        // Advanced (accountant) path — unchanged multi-line journal.
        if ($kind === JournalEntry::KIND_GENERAL) {
            return $this->storeAdvanced($request);
        }

        $data = $this->validateSimple($request, $kind);

        try {
            $this->journals->createSimple($kind, $data, $request->file('attachment'));
        } catch (ValidationException $e) {
            return back()->withInput()->withErrors($e->errors());
        }

        Alert::success('Transaksi berhasil dicatat.')->flash();
        return redirect(backpack_url('accounting/journal'));
    }

    public function edit(int $id)
    {
        $this->guardEdit();
        $entry = JournalEntry::with('lines')->findOrFail($id);

        if ($entry->isLocked()) {
            Alert::error('Jurnal otomatis tidak bisa diedit. Gunakan jurnal pembalik.')->flash();
            return redirect(backpack_url('accounting/journal'));
        }

        // Advanced entries → raw form; simple ones → friendly form.
        if ($entry->kind === JournalEntry::KIND_GENERAL) {
            return view('admin.accounting.journal_form', [
                'entry'        => $entry,
                'accounts'     => Account::active()->orderBy('code')->get(),
                'accountsJson' => Account::active()->orderBy('code')->get()
                    ->map(fn ($a) => ['id' => $a->id, 'label' => "({$a->code}) {$a->name}"])->values(),
                'presetLines'  => $entry->lines->map(fn ($l) => [
                    'account_id' => $l->account_id, 'debit' => (float) $l->debit, 'credit' => (float) $l->credit,
                ])->values(),
            ]);
        }

        return view('admin.accounting.transaction_form', $this->simpleFormData($entry->kind, $entry));
    }

    public function update(Request $request, int $id)
    {
        $this->guardEdit();
        $entry = JournalEntry::with('lines')->findOrFail($id);
        $kind = $request->input('kind', $entry->kind);

        if ($kind === JournalEntry::KIND_GENERAL) {
            return $this->updateAdvanced($request, $entry);
        }

        $data = $this->validateSimple($request, $kind);

        try {
            $this->journals->updateSimple(
                $entry, $kind, $data,
                $request->file('attachment'),
                $request->boolean('remove_attachment')
            );
        } catch (ValidationException $e) {
            return back()->withInput()->withErrors($e->errors());
        }

        Alert::success('Transaksi berhasil diperbarui.')->flash();
        return redirect(backpack_url('accounting/journal'));
    }

    public function destroy(int $id)
    {
        $this->guardEdit();
        $entry = JournalEntry::findOrFail($id);

        try {
            $this->journals->deleteManual($entry);
        } catch (ValidationException $e) {
            Alert::error($e->getMessage())->flash();
            return back();
        }

        Alert::success('Transaksi dihapus.')->flash();
        return back();
    }

    public function reverse(int $id)
    {
        $this->guardEdit();
        $entry = JournalEntry::with('lines')->findOrFail($id);

        try {
            $this->journals->reverse($entry);
        } catch (ValidationException $e) {
            Alert::error($e->getMessage())->flash();
            return back();
        }

        Alert::success('Jurnal pembalik berhasil dibuat.')->flash();
        return back();
    }

    /** Stream a transaction's attachment after an access check. */
    public function attachment(int $id)
    {
        abort_unless(backpack_user()?->can('accounting.view'), 403);
        $entry = JournalEntry::findOrFail($id);

        $disk = $this->journals->disk();

        abort_unless($entry->hasAttachment()
            && $disk->exists($entry->attachment_path), 404, 'Lampiran tidak ditemukan.');

        return $disk->download($entry->attachment_path, $entry->attachment_name);
    }

    // ── helpers ────────────────────────────────────────────

    private function simpleFormData(string $kind, ?JournalEntry $entry): array
    {
        // Prefill amount/accounts from existing lines when editing.
        $prefill = ['amount' => null, 'cash_account_id' => null, 'category_account_id' => null,
                    'from_account_id' => null, 'to_account_id' => null];

        if ($entry) {
            $debit = $entry->lines->firstWhere(fn ($l) => $l->debit > 0);
            $credit = $entry->lines->firstWhere(fn ($l) => $l->credit > 0);
            $amount = $debit?->debit;
            $prefill['amount'] = $amount ? (float) $amount : null;

            if ($kind === JournalEntry::KIND_EXPENSE) {
                $prefill['category_account_id'] = $debit?->account_id;   // beban = debit
                $prefill['cash_account_id'] = $credit?->account_id;      // kas = credit
            } elseif ($kind === JournalEntry::KIND_INCOME) {
                $prefill['cash_account_id'] = $debit?->account_id;       // kas = debit
                $prefill['category_account_id'] = $credit?->account_id;  // pendapatan = credit
            } elseif ($kind === JournalEntry::KIND_TRANSFER) {
                $prefill['to_account_id'] = $debit?->account_id;         // tujuan = debit
                $prefill['from_account_id'] = $credit?->account_id;      // asal = credit
            }
        }

        return [
            'kind'         => $kind,
            'entry'        => $entry,
            'cashAccounts' => Account::cash()->orderBy('code')->get(),
            'expenseAccounts' => Account::expenses()->orderBy('code')->get(),
            'incomeAccounts'  => Account::incomes()->orderBy('code')->get(),
            'prefill'      => $prefill,
        ];
    }

    private function validateSimple(Request $request, string $kind): array
    {
        $rules = [
            'date'        => 'required|date',
            'description' => 'required|string|max:255',
            'amount'      => 'required|numeric|min:0.01',
            'attachment'  => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:10240',
        ];
        if ($kind === JournalEntry::KIND_TRANSFER) {
            $rules['from_account_id'] = 'required|exists:accounts,id';
            $rules['to_account_id']   = 'required|exists:accounts,id|different:from_account_id';
        } else {
            $rules['cash_account_id']     = 'required|exists:accounts,id';
            $rules['category_account_id'] = 'required|exists:accounts,id';
        }

        return $request->validate($rules, [
            'amount.required'  => 'Jumlah wajib diisi.',
            'amount.min'       => 'Jumlah harus lebih dari nol.',
            'to_account_id.different' => 'Akun tujuan harus berbeda dari akun asal.',
            'attachment.mimes' => 'Lampiran harus berupa gambar (jpg/png) atau PDF.',
            'attachment.max'   => 'Ukuran lampiran maksimal 10 MB.',
        ]);
    }

    private function storeAdvanced(Request $request)
    {
        $data = $request->validate([
            'date'        => 'required|date',
            'description' => 'required|string|max:255',
            'lines'       => 'required|array|min:2',
        ]);

        try {
            $this->journals->createManual($data['date'], $data['description'], $data['lines']);
        } catch (ValidationException $e) {
            return back()->withInput()->withErrors($e->errors());
        }

        Alert::success('Jurnal manual berhasil dibuat.')->flash();
        return redirect(backpack_url('accounting/journal'));
    }

    private function updateAdvanced(Request $request, JournalEntry $entry)
    {
        $data = $request->validate([
            'date'        => 'required|date',
            'description' => 'required|string|max:255',
            'lines'       => 'required|array|min:2',
        ]);

        try {
            $this->journals->updateManual($entry, $data['date'], $data['description'], $data['lines']);
        } catch (ValidationException $e) {
            return back()->withInput()->withErrors($e->errors());
        }

        Alert::success('Jurnal berhasil diperbarui.')->flash();
        return redirect(backpack_url('accounting/journal'));
    }
}
