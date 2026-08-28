<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;

/**
 * M15 — Central platform configuration.
 *
 * Reads/writes the `settings` table with an in-memory + persistent cache so it
 * is cheap to call from anywhere. Secret values (tokens, keys) are stored
 * encrypted at rest. Every key has a definition (group/type/default) so the UI
 * can render the right control and callers get a sane fallback to config()/.env
 * when the DB has no row yet.
 */
class SettingService
{
    private const CACHE_KEY = 'app.settings.all';

    /**
     * Definition of every managed setting.
     *
     * fallback = read from config()/.env when the row is missing, so the app
     * keeps working during the transition away from .env.
     *
     * @return array<string, array{group:string, type:string, label:string, encrypted?:bool, fallback?:string, options?:array, help?:string}>
     */
    public static function definitions(): array
    {
        return [
            // ── Umum ───────────────────────────────────────────
            'app_name' => [
                'group' => 'umum', 'type' => 'string', 'label' => 'Nama Aplikasi',
                'fallback' => 'app.name',
            ],

            // ── Lokasi / Geofence ──────────────────────────────
            'office_lat' => [
                'group' => 'lokasi', 'type' => 'string', 'label' => 'Latitude Kantor',
                'fallback' => 'app.office_lat', 'help' => 'Koordinat pusat geofence global (fallback bila cabang tanpa koordinat).',
            ],
            'office_lng' => [
                'group' => 'lokasi', 'type' => 'string', 'label' => 'Longitude Kantor',
                'fallback' => 'app.office_lng',
            ],
            'office_radius' => [
                'group' => 'lokasi', 'type' => 'int', 'label' => 'Radius Geofence (meter)',
                'fallback' => 'app.office_radius',
            ],

            // ── Mode Absensi (M22 — QR vs Camera Location) ─────
            'attendance_mode' => [
                'group' => 'lokasi', 'type' => 'select', 'label' => 'Mode Absensi',
                'options' => ['qr' => 'QR Mode (scanner di pintu)', 'camera' => 'Camera Location Mode (absen mandiri)'],
                'help' => 'QR = pemindai bersama di pintu. Camera = karyawan absen mandiri dari HP (selfie + lokasi + peta).',
            ],
            'camera_require_selfie' => [
                'group' => 'lokasi', 'type' => 'bool', 'label' => 'Wajib Selfie (Camera Mode)',
                'help' => 'Jika aktif, karyawan wajib mengambil foto saat absen mandiri.',
            ],

            // ── Notifikasi (WhatsApp via WAHA self-hosted) ─────
            'whatsapp_enabled' => [
                'group' => 'notifikasi', 'type' => 'bool', 'label' => 'Aktifkan WhatsApp',
                'help' => 'Jika nonaktif, notifikasi WA hanya dicatat di log.',
            ],
            // WAHA (self-hosted container) — https://waha.devlike.pro
            'waha_url' => [
                'group' => 'notifikasi', 'type' => 'string', 'label' => 'WAHA Base URL',
                'help' => 'Mis. http://waha:3000 (nama service container) atau http://localhost:3000.',
            ],
            'waha_session' => [
                'group' => 'notifikasi', 'type' => 'string', 'label' => 'WAHA Session',
                'help' => 'Nama session WAHA. Default: default.',
            ],
            'waha_api_key' => [
                'group' => 'notifikasi', 'type' => 'password', 'label' => 'WAHA API Key (opsional)',
                'encrypted' => true,
                'help' => 'Isi jika WAHA di-set dengan WAHA_API_KEY (header X-Api-Key).',
            ],

            // ── Akuntansi (integrasi eksternal) ────────────────
            'acc_mode' => [
                'group' => 'akuntansi', 'type' => 'select', 'label' => 'Mode Akuntansi',
                'options' => ['internal' => 'Buku Besar Internal', 'firefly' => 'Firefly III (eksternal)'],
                'help' => 'Internal = catat sendiri (disarankan). Firefly = kirim ke sistem akuntansi eksternal.',
            ],
            'acc_active' => [
                'group' => 'akuntansi', 'type' => 'bool', 'label' => 'Aktifkan Sinkronisasi Akuntansi',
                'fallback' => 'services.acc.active',
                'help' => 'Bila nonaktif, transaksi TIDAK dikirim ke sistem akuntansi (tidak diam-diam).',
            ],
            'acc_host' => [
                'group' => 'akuntansi', 'type' => 'string', 'label' => 'Host API Akuntansi',
                'fallback' => 'services.acc.host',
            ],
            'acc_key' => [
                'group' => 'akuntansi', 'type' => 'password', 'label' => 'API Key Akuntansi',
                'encrypted' => true, 'fallback' => 'services.acc.key',
            ],

            // ── Storage ────────────────────────────────────────
            'storage_provider' => [
                'group' => 'storage', 'type' => 'select', 'label' => 'Provider Penyimpanan',
                'options' => [
                    'local'  => 'Lokal (server)',
                    's3'     => 'Amazon S3 / S3-compatible (MinIO, Wasabi, R2)',
                    'google' => 'Google Drive',
                    'webdav' => 'Nextcloud / ownCloud (WebDAV)',
                ],
                'fallback' => 'filesystems.default',
                'help' => 'Tempat menyimpan berkas dokumen & lampiran. Isi kredensial di bawah lalu Tes Koneksi.',
            ],
            // S3 / S3-compatible
            'storage_s3_key' => [
                'group' => 'storage', 'type' => 'password', 'label' => 'S3 Access Key ID',
                'encrypted' => true, 'fallback' => 'filesystems.disks.s3.key',
            ],
            'storage_s3_secret' => [
                'group' => 'storage', 'type' => 'password', 'label' => 'S3 Secret Access Key',
                'encrypted' => true, 'fallback' => 'filesystems.disks.s3.secret',
            ],
            'storage_s3_region' => [
                'group' => 'storage', 'type' => 'string', 'label' => 'S3 Region',
                'fallback' => 'filesystems.disks.s3.region',
                'help' => 'Mis. ap-southeast-1. Untuk MinIO/R2 boleh diisi us-east-1.',
            ],
            'storage_s3_bucket' => [
                'group' => 'storage', 'type' => 'string', 'label' => 'S3 Bucket',
                'fallback' => 'filesystems.disks.s3.bucket',
            ],
            'storage_s3_endpoint' => [
                'group' => 'storage', 'type' => 'string', 'label' => 'S3 Endpoint (opsional)',
                'help' => 'Kosongkan untuk AWS. Isi untuk S3-compatible: MinIO/Wasabi/Cloudflare R2.',
            ],
            'storage_s3_path_style' => [
                'group' => 'storage', 'type' => 'bool', 'label' => 'Gunakan Path-Style Endpoint',
                'help' => 'Aktifkan untuk MinIO / Cloudflare R2.',
            ],
            // Google Drive (OAuth). Kredensial dari Google Cloud Console + OAuth Playground.
            'storage_gdrive_client_id' => [
                'group' => 'storage', 'type' => 'password', 'label' => 'Google Drive Client ID',
                'encrypted' => true,
                'help' => 'Dari Google Cloud Console → Credentials → OAuth 2.0 Client ID.',
            ],
            'storage_gdrive_client_secret' => [
                'group' => 'storage', 'type' => 'password', 'label' => 'Google Drive Client Secret',
                'encrypted' => true,
            ],
            'storage_gdrive_refresh_token' => [
                'group' => 'storage', 'type' => 'password', 'label' => 'Google Drive Refresh Token',
                'encrypted' => true,
                'help' => 'Buat via OAuth Playground (scope Drive) dgn client ID/secret di atas.',
            ],
            'storage_gdrive_folder' => [
                'group' => 'storage', 'type' => 'string', 'label' => 'Folder Tujuan (opsional)',
                'help' => 'Nama/ID folder di Drive. Kosongkan untuk root. Mis. "HRIS-Dokumen".',
            ],
            // Nextcloud / ownCloud / WebDAV
            'storage_webdav_base_uri' => [
                'group' => 'storage', 'type' => 'string', 'label' => 'WebDAV Base URI',
                'help' => 'Mis. https://cloud.contoh.com/remote.php/dav/files/NAMAUSER/',
            ],
            'storage_webdav_username' => [
                'group' => 'storage', 'type' => 'string', 'label' => 'WebDAV Username',
            ],
            'storage_webdav_password' => [
                'group' => 'storage', 'type' => 'password', 'label' => 'WebDAV Password / App Password',
                'encrypted' => true,
                'help' => 'Disarankan pakai App Password Nextcloud (Settings → Security).',
            ],
            'storage_webdav_prefix' => [
                'group' => 'storage', 'type' => 'string', 'label' => 'Subfolder (opsional)',
                'help' => 'Path awalan di dalam WebDAV, mis. "HRIS". Kosongkan untuk root.',
            ],

            // ── Lokalisasi ─────────────────────────────────────
            'default_locale' => [
                'group' => 'lokalisasi', 'type' => 'select', 'label' => 'Bahasa Default',
                'options' => ['id' => 'Indonesia', 'en' => 'English'],
                'fallback' => 'app.locale',
            ],
            'default_currency' => [
                'group' => 'lokalisasi', 'type' => 'select', 'label' => 'Mata Uang Default',
                'options' => ['IDR' => 'Rupiah (Rp)', 'USD' => 'US Dollar ($)', 'EUR' => 'Euro (€)'],
                'fallback' => null,
            ],

            // ── Rekrutmen AI (M17) — Qdrant + embedding + LLM scoring ──
            'qdrant_url' => [
                'group' => 'rekrutmen_ai', 'type' => 'string', 'label' => 'Qdrant Base URL',
                'help' => 'Mis. http://qdrant:6333 (nama service) atau http://localhost:6333.',
            ],
            'qdrant_api_key' => [
                'group' => 'rekrutmen_ai', 'type' => 'password', 'label' => 'Qdrant API Key (opsional)',
                'encrypted' => true, 'help' => 'Isi bila Qdrant dilindungi API key.',
            ],
            // Embedding (Tahap 1 — shortlist)
            'embedding_provider' => [
                'group' => 'rekrutmen_ai', 'type' => 'select', 'label' => 'Provider Embedding',
                'options' => ['openai' => 'OpenAI', 'custom' => 'Custom (OpenAI-compatible)'],
                'help' => 'Untuk menyaring pelamar (shortlist) via kemiripan vektor.',
            ],
            'embedding_model' => [
                'group' => 'rekrutmen_ai', 'type' => 'string', 'label' => 'Model Embedding',
                'help' => 'Mis. text-embedding-3-small (OpenAI) atau nomic-embed-text (Ollama).',
            ],
            'embedding_base_url' => [
                'group' => 'rekrutmen_ai', 'type' => 'string', 'label' => 'Embedding Base URL (Custom)',
                'help' => 'Endpoint OpenAI-compatible, mis. http://localhost:20128/v1.',
            ],
            'embedding_api_key' => [
                'group' => 'rekrutmen_ai', 'type' => 'password', 'label' => 'Embedding API Key',
                'encrypted' => true,
            ],
            // LLM Scoring (Tahap 2 — penilaian rubrik)
            'llm_provider' => [
                'group' => 'rekrutmen_ai', 'type' => 'select', 'label' => 'Provider LLM Scoring',
                'options' => ['openai' => 'OpenAI', 'custom' => 'Custom (OpenAI-compatible)'],
                'help' => 'Untuk menilai pelamar terhadap rubrik (scoring_prompt) lowongan.',
            ],
            'llm_model' => [
                'group' => 'rekrutmen_ai', 'type' => 'string', 'label' => 'Model LLM Scoring',
                'help' => 'Mis. gpt-4o-mini, atau model apa pun di endpoint custom.',
            ],
            'llm_base_url' => [
                'group' => 'rekrutmen_ai', 'type' => 'string', 'label' => 'LLM Base URL (Custom)',
                'help' => 'Endpoint OpenAI-compatible chat/completions.',
            ],
            'llm_api_key' => [
                'group' => 'rekrutmen_ai', 'type' => 'password', 'label' => 'LLM API Key',
                'encrypted' => true,
            ],
            'recruitment_shortlist_size' => [
                'group' => 'rekrutmen_ai', 'type' => 'int', 'label' => 'Ukuran Shortlist (Top-N)',
                'help' => 'Berapa pelamar teratas (dari Qdrant) yang dinilai LLM. Default 30.',
            ],
            'recruitment_reject_action' => [
                'group' => 'rekrutmen_ai', 'type' => 'select', 'label' => 'Aksi Saat Reject',
                'options' => ['delete' => 'Hapus CV permanen', 'archive' => 'Arsip CV'],
                'help' => 'Default: hapus permanen (keputusan kebijakan).',
            ],
            'recruitment_cv_retention_days' => [
                'group' => 'rekrutmen_ai', 'type' => 'int', 'label' => 'Retensi CV Rejected (hari)',
                'help' => 'CV pelamar yang direject dihapus setelah sekian hari. Default 30.',
            ],
            'recruitment_ghost_retention_days' => [
                'group' => 'rekrutmen_ai', 'type' => 'int', 'label' => 'Retensi CV Ghosting (hari)',
                'help' => 'CV pelamar non-hired dihapus sekian hari setelah lowongan DITUTUP. Default 90.',
            ],
            'recruitment_archive_disk' => [
                'group' => 'rekrutmen_ai', 'type' => 'string', 'label' => 'Disk Arsip CV (opsional)',
                'help' => 'Nama disk tujuan arsip saat aksi reject = "Arsip CV". Kosongkan untuk ikut '
                        . 'provider penyimpanan aktif (Pengaturan → Penyimpanan). Tak ada disk dipatok di kode.',
            ],
        ];
    }

    /**
     * Group labels for the tabbed UI.
     *
     * @return array<string,string>
     */
    public static function groups(): array
    {
        return [
            'umum'       => 'Umum',
            'lokasi'     => 'Lokasi & Geofence',
            'notifikasi' => 'Notifikasi (WhatsApp)',
            'akuntansi'  => 'Akuntansi',
            'storage'    => 'Penyimpanan',
            'lokalisasi' => 'Bahasa & Mata Uang',
            'rekrutmen_ai' => 'Rekrutmen AI',
        ];
    }

    /**
     * Raw stored values keyed by setting key (decrypted), cached.
     *
     * @return array<string,mixed>
     */
    public function all(): array
    {
        // During early boot / before migration the table may not exist yet.
        if (! Schema::hasTable('settings')) {
            return [];
        }

        return Cache::rememberForever(self::CACHE_KEY, function () {
            $out = [];
            foreach (Setting::all() as $row) {
                $value = $row->value;
                if ($row->is_encrypted && $value !== null && $value !== '') {
                    try {
                        $value = Crypt::decryptString($value);
                    } catch (\Throwable $e) {
                        $value = null; // corrupt/rotated key — treat as unset
                    }
                }
                $out[$row->key] = $value;
            }

            return $out;
        });
    }

    /**
     * Get a setting value with type-casting, falling back to config()/.env when
     * the row does not exist.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $defs = self::definitions();
        $stored = $this->all();

        if (array_key_exists($key, $stored) && $stored[$key] !== null && $stored[$key] !== '') {
            return $this->cast($stored[$key], $defs[$key]['type'] ?? 'string');
        }

        // Fallback to config()/.env so nothing breaks before a value is saved.
        if (isset($defs[$key]['fallback']) && $defs[$key]['fallback']) {
            $fallbackVal = config($defs[$key]['fallback']);
            if ($fallbackVal !== null) {
                return $this->cast($fallbackVal, $defs[$key]['type'] ?? 'string');
            }
        }

        return $default;
    }

    /**
     * Persist a single setting (encrypting if defined as secret) and bust cache.
     */
    public function set(string $key, mixed $value): void
    {
        $defs = self::definitions();
        $def = $defs[$key] ?? ['group' => 'umum', 'type' => 'string'];
        $encrypted = ! empty($def['encrypted']);

        $store = $value;
        if ($encrypted && $value !== null && $value !== '') {
            $store = Crypt::encryptString((string) $value);
        }

        Setting::updateOrCreate(
            ['key' => $key],
            [
                'value'        => $store,
                'group'        => $def['group'] ?? 'umum',
                'type'         => $def['type'] ?? 'string',
                'is_encrypted' => $encrypted,
            ]
        );

        $this->flush();
    }

    /**
     * Bulk save (used by the settings form). Skips password fields left blank so
     * an unchanged secret is not wiped.
     *
     * @param array<string,mixed> $values
     */
    public function setMany(array $values): void
    {
        $defs = self::definitions();
        foreach ($values as $key => $value) {
            if (! isset($defs[$key])) {
                continue;
            }
            // Don't overwrite a stored secret with an empty submission.
            if (($defs[$key]['type'] ?? '') === 'password' && ($value === null || $value === '')) {
                continue;
            }
            $this->set($key, $value);
        }
    }

    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    private function cast(mixed $value, string $type): mixed
    {
        return match ($type) {
            'bool'  => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'int'   => (int) $value,
            'float' => (float) $value,
            default => $value,
        };
    }
}
