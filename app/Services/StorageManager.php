<?php

namespace App\Services;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * M16 — Pluggable storage backends.
 *
 * Builds a filesystem disk at runtime from the M15 platform settings instead of
 * config/filesystems.php, so a customer can connect their own storage (S3 /
 * MinIO / Wasabi / R2 in phase 1) entirely from the Settings UI — no .env edit,
 * no deploy.
 *
 * Everything that persists uploaded files (employee documents, journal
 * attachments, …) resolves its disk through here so one setting change moves
 * them all.
 */
class StorageManager
{
    /** Fallback disk when nothing is configured or config is invalid. */
    public const DEFAULT = 'local';

    /**
     * Active provider key: 'local' | 's3' | 'google'. Falls back to local.
     */
    public function provider(): string
    {
        $p = (string) setting('storage_provider', self::DEFAULT);

        return in_array($p, ['local', 's3', 'google', 'webdav'], true) ? $p : self::DEFAULT;
    }

    /**
     * Build the on-demand disk config array for the active provider.
     * Returns null for 'local' (use the framework's own local disk).
     *
     * @return array<string, mixed>|null
     */
    public function diskConfig(): ?array
    {
        return match ($this->provider()) {
            's3'     => $this->s3Config(),
            'google' => $this->googleConfig(),
            'webdav' => $this->webdavConfig(),
            default  => null,
        };
    }

    /** @return array<string, mixed> */
    private function s3Config(): array
    {
        $endpoint = trim((string) setting('storage_s3_endpoint', ''));

        $config = [
            'driver'   => 's3',
            'key'      => (string) setting('storage_s3_key', config('filesystems.disks.s3.key')),
            'secret'   => (string) setting('storage_s3_secret', config('filesystems.disks.s3.secret')),
            'region'   => (string) setting('storage_s3_region', config('filesystems.disks.s3.region')) ?: 'us-east-1',
            'bucket'   => (string) setting('storage_s3_bucket', config('filesystems.disks.s3.bucket')),
            'throw'    => true, // surface errors to testConnection instead of silently failing
        ];

        if ($endpoint !== '') {
            $config['endpoint'] = $endpoint;
            // MinIO / R2 need path-style addressing.
            $config['use_path_style_endpoint'] = (bool) setting('storage_s3_path_style', false);
        }

        return $config;
    }

    /** @return array<string, mixed> */
    private function googleConfig(): array
    {
        $folder = trim((string) setting('storage_gdrive_folder', ''));

        return [
            'driver'       => 'google',
            'clientId'     => (string) setting('storage_gdrive_client_id', ''),
            'clientSecret' => (string) setting('storage_gdrive_client_secret', ''),
            'refreshToken' => (string) setting('storage_gdrive_refresh_token', ''),
            'folder'       => $folder !== '' ? $folder : '/',
            'throw'        => true,
        ];
    }

    /** @return array<string, mixed> */
    private function webdavConfig(): array
    {
        return [
            'driver'   => 'webdav',
            'baseUri'  => (string) setting('storage_webdav_base_uri', ''),
            'userName' => (string) setting('storage_webdav_username', ''),
            'password' => (string) setting('storage_webdav_password', ''),
            'prefix'   => trim((string) setting('storage_webdav_prefix', '')),
            'throw'    => true,
        ];
    }

    /**
     * The active disk instance. For 'local' this is the framework's private
     * local disk; for 's3' it is built on demand from settings.
     */
    public function disk(): Filesystem
    {
        $config = $this->diskConfig();

        if ($config === null) {
            return Storage::disk(self::DEFAULT);
        }

        try {
            return Storage::build($config);
        } catch (\Throwable $e) {
            Log::error('[Storage] failed to build disk, falling back to local', [
                'provider' => $this->provider(),
                'message'  => $e->getMessage(),
            ]);

            return Storage::disk(self::DEFAULT);
        }
    }

    /**
     * Human-readable label for the active provider (for status panels).
     */
    public function label(): string
    {
        return match ($this->provider()) {
            's3'     => trim((string) setting('storage_s3_endpoint', '')) !== ''
                        ? 'S3-compatible (' . setting('storage_s3_endpoint') . ')'
                        : 'Amazon S3',
            'google' => 'Google Drive',
            'webdav' => trim((string) setting('storage_webdav_base_uri', '')) !== ''
                        ? 'Nextcloud/WebDAV (' . setting('storage_webdav_base_uri') . ')'
                        : 'Nextcloud / WebDAV',
            default  => 'Lokal (server)',
        };
    }

    /**
     * Actually write, read back, and delete a probe file so the admin knows the
     * connection truly works — not just that fields are filled.
     *
     * @return array{ok:bool, message:string}
     */
    public function testConnection(): array
    {
        $provider = $this->provider();

        if ($provider === 'local') {
            return ['ok' => true, 'message' => 'Penyimpanan lokal aktif (server).'];
        }

        $probe = 'health/probe_' . Str::uuid()->toString() . '.txt';
        $token = 'ok-' . now()->timestamp;

        try {
            $disk = $this->disk();
            $disk->put($probe, $token);

            $readBack = $disk->get($probe);
            $disk->delete($probe);

            if ($readBack !== $token) {
                return ['ok' => false, 'message' => 'Berkas uji tertulis tapi isi tidak cocok saat dibaca ulang.'];
            }

            return ['ok' => true, 'message' => 'Koneksi berhasil — tulis, baca, hapus berkas uji OK.'];
        } catch (\Throwable $e) {
            Log::warning('[Storage] test connection failed', [
                'provider' => $provider,
                'message'  => $e->getMessage(),
            ]);

            return ['ok' => false, 'message' => 'Gagal terhubung: ' . $e->getMessage()];
        }
    }
}
