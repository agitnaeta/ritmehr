<?php

namespace App\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * M12 — A chart-of-accounts entry in the internal general ledger.
 */
class Account extends Model
{
    use CrudTrait;
    use HasFactory;

    public const TYPE_ASSET     = 'asset';
    public const TYPE_LIABILITY = 'liability';
    public const TYPE_EQUITY    = 'equity';
    public const TYPE_INCOME    = 'income';
    public const TYPE_EXPENSE   = 'expense';

    // Functional roles used by auto-posting (M12). An account tagged with a
    // role is the one the ledger uses for that purpose — no separate mapping
    // table. Roles are unique in practice (one cash account, etc.).
    public const ROLE_CASH            = 'cash';
    public const ROLE_SALARY_EXPENSE  = 'salary_expense';
    public const ROLE_LOAN_RECEIVABLE = 'loan_receivable';

    protected $fillable = ['code', 'name', 'type', 'role', 'is_cash', 'is_active'];

    protected $casts = ['is_active' => 'boolean', 'is_cash' => 'boolean'];

    // ── Scopes for the friendly "Catat Transaksi" dropdowns ──
    public function scopeActive($q) { return $q->where('is_active', true); }
    public function scopeCash($q) { return $q->where('is_cash', true)->where('is_active', true); }
    public function scopeExpenses($q) { return $q->where('type', self::TYPE_EXPENSE)->where('is_active', true); }
    public function scopeIncomes($q) { return $q->where('type', self::TYPE_INCOME)->where('is_active', true); }

    /**
     * Human labels for the assignable posting roles.
     *
     * @return array<string,string>
     */
    public static function roleOptions(): array
    {
        return [
            self::ROLE_CASH            => 'Kas / Bank (sumber pembayaran)',
            self::ROLE_SALARY_EXPENSE  => 'Beban Gaji',
            self::ROLE_LOAN_RECEIVABLE => 'Piutang Karyawan (Kasbon)',
        ];
    }

    /** The account tagged with a given posting role, or null. */
    public static function forRole(string $role): ?self
    {
        return static::where('role', $role)->where('is_active', true)->first();
    }

    public function roleLabel(): ?string
    {
        return $this->role ? (self::roleOptions()[$this->role] ?? $this->role) : null;
    }

    /** Nama akun + saldo berjalan — dipakai di dropdown Kas/Bank agar user tahu isi tiap akun. */
    public function cashLabel(): string
    {
        return $this->name . ' — ' . money($this->balance());
    }

    public function lines()
    {
        return $this->hasMany(JournalLine::class);
    }

    /**
     * Normal-balance-aware balance.
     *
     * Assets & expenses increase with debits; liabilities, equity & income
     * increase with credits. Returned as a positive number in the account's
     * natural direction.
     */
    public function balance(): float
    {
        $debit = (float) $this->lines()->sum('debit');
        $credit = (float) $this->lines()->sum('credit');

        return in_array($this->type, [self::TYPE_ASSET, self::TYPE_EXPENSE], true)
            ? $debit - $credit
            : $credit - $debit;
    }

    public function typeLabel(): string
    {
        return [
            self::TYPE_ASSET     => 'Aset',
            self::TYPE_LIABILITY => 'Kewajiban',
            self::TYPE_EQUITY    => 'Ekuitas',
            self::TYPE_INCOME    => 'Pendapatan',
            self::TYPE_EXPENSE   => 'Beban',
        ][$this->type] ?? $this->type;
    }
}
