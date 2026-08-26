<?php

namespace App\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * M12 — A journal entry (one accounting transaction) in the internal ledger.
 * Composed of two or more balanced lines.
 */
class JournalEntry extends Model
{
    use CrudTrait;
    use HasFactory;

    protected $fillable = ['date', 'description', 'reference', 'source', 'kind', 'external_id', 'reversed_entry_id', 'attachment_path', 'attachment_name'];

    protected $casts = ['date' => 'date'];

    public const SOURCE_MANUAL = 'manual';

    // Which simple form produced the entry (drives Edit).
    public const KIND_EXPENSE  = 'expense';
    public const KIND_INCOME   = 'income';
    public const KIND_TRANSFER = 'transfer';
    public const KIND_GENERAL  = 'general';

    public static function kindLabels(): array
    {
        return [
            self::KIND_EXPENSE  => 'Pengeluaran',
            self::KIND_INCOME   => 'Pemasukan',
            self::KIND_TRANSFER => 'Transfer',
            self::KIND_GENERAL  => 'Manual',
        ];
    }

    public function kindLabel(): string
    {
        return self::kindLabels()[$this->kind] ?? 'Manual';
    }

    public function hasAttachment(): bool
    {
        return ! empty($this->attachment_path);
    }

    public function lines()
    {
        return $this->hasMany(JournalLine::class);
    }

    /** The entry this one reverses (if it is a reversal). */
    public function reversedEntry()
    {
        return $this->belongsTo(JournalEntry::class, 'reversed_entry_id');
    }

    /** Reversal entries that cancel this one. */
    public function reversals()
    {
        return $this->hasMany(JournalEntry::class, 'reversed_entry_id');
    }

    /**
     * Only manual entries may be edited or deleted from the accounting UI.
     * Auto-posted entries (salary/loan/loan_payment) are locked to keep the
     * ledger in sync with their source documents — correct them via a reversal.
     */
    public function isManual(): bool
    {
        return $this->source === self::SOURCE_MANUAL;
    }

    public function isLocked(): bool
    {
        return ! $this->isManual();
    }

    /** Already reversed? (avoid double-reversing) */
    public function isReversed(): bool
    {
        return $this->reversals()->exists();
    }

    public function isReversal(): bool
    {
        return $this->reversed_entry_id !== null;
    }

    public function totalDebit(): float
    {
        return (float) $this->lines->sum('debit');
    }

    public function totalCredit(): float
    {
        return (float) $this->lines->sum('credit');
    }

    /** A well-formed entry has equal debits and credits. */
    public function isBalanced(): bool
    {
        return round($this->totalDebit() - $this->totalCredit(), 2) === 0.0;
    }
}
