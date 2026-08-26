<?php

namespace Tests\Feature;

use App\Services\Notifications\LogWhatsAppGateway;
use App\Services\Notifications\WahaWhatsAppGateway;
use App\Services\Notifications\WhatsAppGateway;
use App\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * M03 — WhatsApp via WAHA (self-hosted) as the sole provider, with a log
 * fallback when unconfigured or disabled.
 */
class WahaWhatsAppTest extends TestCase
{
    use RefreshDatabase;

    private function set(array $kv): void
    {
        $s = app(SettingService::class);
        foreach ($kv as $k => $v) {
            $s->set($k, $v);
        }
        $s->flush();
        // Gateway is a singleton; drop any cached instance so it is rebuilt
        // from the settings we just changed.
        $this->app->forgetInstance(WhatsAppGateway::class);
    }

    public function test_waha_gateway_posts_sendtext_with_normalised_chat_id(): void
    {
        Http::fake(['*/api/sendText' => Http::response(['id' => 'x'], 201)]);

        $gateway = new WahaWhatsAppGateway('http://waha:3000', 'default', 'secret');
        $ok = $gateway->send('08123456789', 'halo');

        $this->assertTrue($ok);
        Http::assertSent(function ($req) {
            return $req->url() === 'http://waha:3000/api/sendText'
                && $req['chatId'] === '628123456789@c.us'
                && $req['session'] === 'default'
                && $req['text'] === 'halo'
                && $req->hasHeader('X-Api-Key', 'secret');
        });
    }

    public function test_waha_gateway_omits_api_key_header_when_blank(): void
    {
        Http::fake(['*/api/sendText' => Http::response([], 200)]);

        (new WahaWhatsAppGateway('http://waha:3000', 'default', ''))->send('628999', 'hi');

        Http::assertSent(fn ($req) => ! $req->hasHeader('X-Api-Key'));
    }

    public function test_waha_gateway_returns_false_on_http_error(): void
    {
        Http::fake(['*/api/sendText' => Http::response('unauthorized', 401)]);

        $ok = (new WahaWhatsAppGateway('http://waha:3000'))->send('628999', 'hi');

        $this->assertFalse($ok);
    }

    public function test_existing_chat_id_is_passed_through(): void
    {
        Http::fake(['*/api/sendText' => Http::response([], 200)]);

        (new WahaWhatsAppGateway('http://waha:3000'))->send('628123@c.us', 'hi');

        Http::assertSent(fn ($req) => $req['chatId'] === '628123@c.us');
    }

    public function test_container_resolves_waha_by_default_when_configured(): void
    {
        $this->set([
            'whatsapp_enabled'  => true,
            'waha_url'          => 'http://waha:3000',
        ]);

        $this->assertInstanceOf(WahaWhatsAppGateway::class, app(WhatsAppGateway::class));
    }

    public function test_container_falls_back_to_log_when_unconfigured(): void
    {
        $this->set([
            'whatsapp_enabled'  => true,
            'waha_url'          => '',
        ]);

        $this->assertInstanceOf(LogWhatsAppGateway::class, app(WhatsAppGateway::class));
    }

    public function test_container_uses_log_when_whatsapp_disabled(): void
    {
        $this->set([
            'whatsapp_enabled'  => false,
            'waha_url'          => 'http://waha:3000',
        ]);

        $this->assertInstanceOf(LogWhatsAppGateway::class, app(WhatsAppGateway::class));
    }
}
