<?php

namespace App\Http\Controllers\Admin;

use App\Services\SettingService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Prologue\Alerts\Facades\Alert;

/**
 * M15 — Platform Configuration.
 *
 * Super-admin-only screen to manage third-party credentials and system config
 * that used to live in .env. Grouped into tabs; secrets are write-only (never
 * rendered back to the browser).
 */
class SettingController extends Controller
{
    public function __construct(private readonly SettingService $settings)
    {
    }

    private function guard(): void
    {
        $user = backpack_user();
        abort_unless($user && $user->hasRole('super_admin'), 403, 'Hanya super admin yang dapat mengubah pengaturan sistem.');
    }

    public function index()
    {
        $this->guard();

        $defs = SettingService::definitions();
        $groups = SettingService::groups();

        // Current values, with secrets masked (never send the real token to the browser).
        $values = [];
        foreach ($defs as $key => $def) {
            if (($def['type'] ?? '') === 'password') {
                $values[$key] = $this->settings->get($key) ? '********' : '';
            } else {
                $values[$key] = $this->settings->get($key);
            }
        }

        return view('admin.settings.index', [
            'defs'    => $defs,
            'groups'  => $groups,
            'values'  => $values,
            'status'  => $this->connectionStatus(),
        ]);
    }

    public function update(Request $request)
    {
        $this->guard();

        $defs = SettingService::definitions();
        $incoming = [];

        foreach ($defs as $key => $def) {
            $type = $def['type'] ?? 'string';

            if ($type === 'bool') {
                // Unchecked checkboxes are absent from the payload.
                $incoming[$key] = $request->boolean($key);
                continue;
            }

            if ($request->has($key)) {
                $val = $request->input($key);
                // Masked password left untouched → skip so we don't wipe the secret.
                if ($type === 'password' && $val === '********') {
                    continue;
                }
                $incoming[$key] = $val;
            }
        }

        $this->settings->setMany($incoming);

        Alert::success('Pengaturan sistem berhasil disimpan.')->flash();

        return redirect(backpack_url('settings'));
    }

    /**
     * M03 — Send a real test WhatsApp message so the admin can confirm the
     * gateway actually works, instead of guessing from a "token filled" badge.
     *
     * In log mode (no token) this still returns success but notes it was logged,
     * so the admin understands nothing left the server.
     */
    public function testWhatsApp(Request $request)
    {
        $this->guard();

        $data = $request->validate([
            'phone' => 'required|string|max:20',
        ], [
            'phone.required' => 'Nomor tujuan wajib diisi.',
        ]);

        // Fresh cache + rebuild so a just-saved config is picked up before testing.
        $this->settings->flush();
        app()->forgetInstance(\App\Services\Notifications\WhatsAppGateway::class);
        $gateway = app(\App\Services\Notifications\WhatsAppGateway::class);
        $isLive = ! ($gateway instanceof \App\Services\Notifications\LogWhatsAppGateway);

        $message = 'Tes koneksi WhatsApp dari ' . config('app.name', 'HRIS')
            . ' pada ' . now()->format('d/m/Y H:i') . '. Abaikan pesan ini.';

        $ok = $gateway->send($data['phone'], $message);

        if (! $isLive) {
            Alert::info('WhatsApp dalam mode log (nonaktif / provider belum dikonfigurasi) — pesan dicatat ke log, tidak dikirim keluar.')->flash();
        } elseif ($ok) {
            Alert::success('Pesan tes WhatsApp berhasil dikirim ke ' . $data['phone'] . '.')->flash();
        } else {
            Alert::error('Gagal mengirim WhatsApp. Cek konfigurasi provider/nomor & lihat log untuk detail.')->flash();
        }

        return redirect(backpack_url('settings'));
    }

    /**
     * M16 — Test the configured storage provider by writing/reading/deleting a
     * probe file, so the admin verifies the connection during onboarding.
     */
    public function testStorage()
    {
        $this->guard();

        // Fresh cache so just-saved credentials are used.
        $this->settings->flush();
        $result = app(\App\Services\StorageManager::class)->testConnection();

        if ($result['ok']) {
            Alert::success('Koneksi penyimpanan OK — ' . $result['message'])->flash();
        } else {
            Alert::error('Koneksi penyimpanan gagal — ' . $result['message'])->flash();
        }

        return redirect(backpack_url('settings'));
    }

    /**
     * M17 — Test the Rekrutmen AI stack: Qdrant reachability, embedding provider,
     * and LLM scoring provider. Real probes (not just "field is filled").
     */
    public function testQdrant()
    {
        $this->guard();
        $this->settings->flush();

        $up = app(\App\Services\Matching\QdrantService::class)->isUp();
        $up
            ? Alert::success('Qdrant OK — terhubung.')->flash()
            : Alert::error('Qdrant gagal — tidak dapat dihubungi. Cek URL/API key.')->flash();

        return redirect(backpack_url('settings'));
    }

    public function testEmbedding()
    {
        $this->guard();
        $this->settings->flush();

        $r = app(\App\Services\Matching\EmbeddingManager::class)->testConnection();
        $r['ok']
            ? Alert::success('Embedding OK — ' . $r['message'])->flash()
            : Alert::error('Embedding gagal — ' . $r['message'])->flash();

        return redirect(backpack_url('settings'));
    }

    public function testLlm()
    {
        $this->guard();
        $this->settings->flush();

        $r = app(\App\Services\Matching\LlmScoringManager::class)->testConnection();
        $r['ok']
            ? Alert::success('LLM Scoring OK — ' . $r['message'])->flash()
            : Alert::error('LLM Scoring gagal — ' . $r['message'])->flash();

        return redirect(backpack_url('settings'));
    }

    /**
     * Live status indicators so the admin isn't guessing whether a third-party
     * integration is actually usable.
     *
     * @return array<string, array{ok:bool, label:string}>
     */
    private function connectionStatus(): array
    {
        $accActive = (bool) $this->settings->get('acc_active');
        $accHost   = (string) $this->settings->get('acc_host');
        $waEnabled = (bool) $this->settings->get('whatsapp_enabled');
        $wahaUrl   = (string) $this->settings->get('waha_url');

        // WhatsApp (WAHA) is "OK" when enabled AND the base URL is configured.
        $waConfigured = $wahaUrl !== '';
        if (! $waEnabled) {
            $waLabel = 'Nonaktif (mode log)';
        } elseif ($waConfigured) {
            $waLabel = 'Aktif — WAHA: ' . $wahaUrl;
        } else {
            $waLabel = 'Aktif tapi WAHA URL kosong (mode log)';
        }

        // Storage (M16): probe the active provider via StorageManager.
        $storage = app(\App\Services\StorageManager::class);
        $storageResult = $storage->testConnection();
        $storageOk = $storageResult['ok'];

        return [
            'acc' => [
                'ok'    => $accActive && $accHost !== '',
                'label' => $accActive
                    ? ($accHost !== '' ? 'Aktif — host terisi' : 'Aktif tapi host kosong')
                    : 'Nonaktif (transaksi tidak dikirim keluar)',
            ],
            'whatsapp' => [
                'ok'    => $waEnabled && $waConfigured,
                'label' => $waLabel,
            ],
            'storage' => [
                'ok'    => $storageOk,
                'label' => $storage->label() . ($storageOk ? ' — OK' : ' — ' . $storageResult['message']),
            ],
        ];
    }
}
