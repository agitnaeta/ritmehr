<?php

namespace App\Services;

/**
 * M14 — Central money formatting.
 *
 * One place decides how amounts render, driven by the platform's default
 * currency setting (M15). Replaces scattered number_format(...,'Rp') calls and
 * the old hardcoded `rupiah` Blade directive.
 */
class CurrencyService
{
    /**
     * Currency definitions: symbol, decimal places, and symbol position.
     * 'before' → "Rp 1.000" / "$1,000"; 'after' → "1.000 Rp".
     *
     * @var array<string, array{symbol:string, decimals:int, position:string, thousands:string, decimal:string}>
     */
    public const CURRENCIES = [
        'IDR' => ['symbol' => 'Rp',  'decimals' => 0, 'position' => 'before', 'thousands' => '.', 'decimal' => ','],
        'USD' => ['symbol' => '$',   'decimals' => 2, 'position' => 'before', 'thousands' => ',', 'decimal' => '.'],
        'EUR' => ['symbol' => '€',   'decimals' => 2, 'position' => 'before', 'thousands' => '.', 'decimal' => ','],
        'SGD' => ['symbol' => 'S$',  'decimals' => 2, 'position' => 'before', 'thousands' => ',', 'decimal' => '.'],
        'MYR' => ['symbol' => 'RM',  'decimals' => 2, 'position' => 'before', 'thousands' => ',', 'decimal' => '.'],
    ];

    public const DEFAULT = 'IDR';

    /** Active currency code from settings, falling back to IDR. */
    public function code(): string
    {
        $code = strtoupper((string) setting('default_currency', self::DEFAULT));
        return isset(self::CURRENCIES[$code]) ? $code : self::DEFAULT;
    }

    /** @return array{symbol:string, decimals:int, position:string, thousands:string, decimal:string} */
    public function meta(?string $code = null): array
    {
        $code = $code ? strtoupper($code) : $this->code();
        return self::CURRENCIES[$code] ?? self::CURRENCIES[self::DEFAULT];
    }

    public function symbol(?string $code = null): string
    {
        return $this->meta($code)['symbol'];
    }

    /**
     * Format an amount using the active (or given) currency.
     * e.g. IDR → "Rp 1.500.000", USD → "$1,500.00".
     */
    public function format($amount, ?string $code = null): string
    {
        $m = $this->meta($code);
        $number = number_format((float) $amount, $m['decimals'], $m['decimal'], $m['thousands']);

        return $m['position'] === 'after'
            ? $number . ' ' . $m['symbol']
            : $m['symbol'] . ' ' . $number;
    }
}
