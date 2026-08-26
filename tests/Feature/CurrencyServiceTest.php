<?php

namespace Tests\Feature;

use App\Services\CurrencyService;
use App\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * M14 — Central currency formatting follows the platform's default_currency
 * setting (M15), with correct symbol, position, decimals and separators.
 */
class CurrencyServiceTest extends TestCase
{
    use RefreshDatabase;

    private function setCurrency(string $code): void
    {
        app(SettingService::class)->set('default_currency', $code);
        app(SettingService::class)->flush();
    }

    public function test_idr_format_symbol_before_no_decimals(): void
    {
        $this->setCurrency('IDR');
        $this->assertSame('Rp 1.500.000', money(1500000));
        $this->assertSame('Rp 0', money(0));
    }

    public function test_usd_format_two_decimals_comma_thousands(): void
    {
        $this->setCurrency('USD');
        $this->assertSame('$ 1,500,000.00', money(1500000));
        $this->assertSame('$ 2,500.50', money(2500.5));
    }

    public function test_eur_format_dot_thousands_comma_decimals(): void
    {
        $this->setCurrency('EUR');
        $this->assertSame('€ 1.500.000,00', money(1500000));
    }

    public function test_explicit_code_overrides_active_setting(): void
    {
        $this->setCurrency('IDR');
        $this->assertSame('$ 2,500.00', money(2500, 'USD'));
        // active setting stays IDR
        $this->assertSame('Rp 2.500', money(2500));
    }

    public function test_unknown_currency_falls_back_to_idr(): void
    {
        $this->setCurrency('XYZ');
        $svc = app(CurrencyService::class);
        $this->assertSame('IDR', $svc->code());
        $this->assertSame('Rp 1.000', money(1000));
    }

    public function test_symbol_helper_matches_active_currency(): void
    {
        $this->setCurrency('USD');
        $this->assertSame('$', app(CurrencyService::class)->symbol());
        $this->setCurrency('IDR');
        $this->assertSame('Rp', app(CurrencyService::class)->symbol());
    }
}
