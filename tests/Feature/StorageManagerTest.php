<?php

namespace Tests\Feature;

use App\Services\SettingService;
use App\Services\StorageManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * M16 — Pluggable storage: the active disk is built at runtime from the M15
 * settings (local / S3-compatible), with a safe fallback to local.
 */
class StorageManagerTest extends TestCase
{
    use RefreshDatabase;

    private function set(array $kv): void
    {
        $s = app(SettingService::class);
        foreach ($kv as $k => $v) {
            $s->set($k, $v);
        }
        $s->flush();
    }

    public function test_defaults_to_local(): void
    {
        $mgr = app(StorageManager::class);

        $this->assertSame('local', $mgr->provider());
        $this->assertNull($mgr->diskConfig());
        $this->assertStringContainsString('Lokal', $mgr->label());
    }

    public function test_local_test_connection_is_ok(): void
    {
        $result = app(StorageManager::class)->testConnection();

        $this->assertTrue($result['ok']);
    }

    public function test_s3_disk_config_is_assembled_from_settings(): void
    {
        $this->set([
            'storage_provider'     => 's3',
            'storage_s3_key'       => 'AKIAEXAMPLE',
            'storage_s3_secret'    => 'secretxyz',
            'storage_s3_region'    => 'ap-southeast-1',
            'storage_s3_bucket'    => 'my-bucket',
            'storage_s3_endpoint'  => 'https://minio.local:9000',
            'storage_s3_path_style' => true,
        ]);

        $config = app(StorageManager::class)->diskConfig();

        $this->assertSame('s3', $config['driver']);
        $this->assertSame('AKIAEXAMPLE', $config['key']);
        $this->assertSame('secretxyz', $config['secret']);
        $this->assertSame('ap-southeast-1', $config['region']);
        $this->assertSame('my-bucket', $config['bucket']);
        $this->assertSame('https://minio.local:9000', $config['endpoint']);
        $this->assertTrue($config['use_path_style_endpoint']);
    }

    public function test_s3_without_endpoint_omits_path_style(): void
    {
        $this->set([
            'storage_provider'  => 's3',
            'storage_s3_key'    => 'k', 'storage_s3_secret' => 's',
            'storage_s3_region' => 'us-east-1', 'storage_s3_bucket' => 'b',
        ]);

        $config = app(StorageManager::class)->diskConfig();

        $this->assertArrayNotHasKey('endpoint', $config);
        $this->assertArrayNotHasKey('use_path_style_endpoint', $config);
    }

    public function test_s3_region_falls_back_when_blank(): void
    {
        $this->set([
            'storage_provider'  => 's3',
            'storage_s3_key'    => 'k', 'storage_s3_secret' => 's',
            'storage_s3_bucket' => 'b',
        ]);

        $config = app(StorageManager::class)->diskConfig();

        $this->assertSame('us-east-1', $config['region']);
    }

    public function test_secrets_are_encrypted_at_rest(): void
    {
        $this->set([
            'storage_provider'  => 's3',
            'storage_s3_secret' => 'topsecret',
        ]);

        $raw = \App\Models\Setting::where('key', 'storage_s3_secret')->value('value');

        // Stored value must not be the plaintext.
        $this->assertNotSame('topsecret', $raw);
        // But the service reads it back correctly.
        $this->assertSame('topsecret', setting('storage_s3_secret'));
    }

    // ── Google Drive (Fase 2) ──────────────────────────────

    public function test_google_provider_is_accepted(): void
    {
        $this->set(['storage_provider' => 'google']);

        $this->assertSame('google', app(StorageManager::class)->provider());
        $this->assertSame('Google Drive', app(StorageManager::class)->label());
    }

    public function test_google_disk_config_is_assembled_from_settings(): void
    {
        $this->set([
            'storage_provider'             => 'google',
            'storage_gdrive_client_id'     => 'cid.apps.googleusercontent.com',
            'storage_gdrive_client_secret' => 'gsecret',
            'storage_gdrive_refresh_token' => 'rtoken',
            'storage_gdrive_folder'        => 'HRIS-Dokumen',
        ]);

        $config = app(StorageManager::class)->diskConfig();

        $this->assertSame('google', $config['driver']);
        $this->assertSame('cid.apps.googleusercontent.com', $config['clientId']);
        $this->assertSame('gsecret', $config['clientSecret']);
        $this->assertSame('rtoken', $config['refreshToken']);
        $this->assertSame('HRIS-Dokumen', $config['folder']);
    }

    public function test_google_folder_defaults_to_root_when_blank(): void
    {
        $this->set([
            'storage_provider'             => 'google',
            'storage_gdrive_client_id'     => 'cid',
            'storage_gdrive_client_secret' => 's',
            'storage_gdrive_refresh_token' => 'r',
        ]);

        $this->assertSame('/', app(StorageManager::class)->diskConfig()['folder']);
    }

    public function test_google_driver_is_registered_and_builds_a_disk(): void
    {
        // The custom 'google' driver (AppServiceProvider::boot) must resolve.
        // Construction is offline; only real operations hit the network.
        $disk = Storage::build([
            'driver' => 'google', 'clientId' => 'x', 'clientSecret' => 'y',
            'refreshToken' => 'z', 'folder' => '/',
        ]);

        $this->assertInstanceOf(\Illuminate\Filesystem\FilesystemAdapter::class, $disk);
    }

    public function test_google_secrets_are_encrypted_at_rest(): void
    {
        $this->set(['storage_gdrive_refresh_token' => 'my-refresh-token']);

        $raw = \App\Models\Setting::where('key', 'storage_gdrive_refresh_token')->value('value');

        $this->assertNotSame('my-refresh-token', $raw);
        $this->assertSame('my-refresh-token', setting('storage_gdrive_refresh_token'));
    }

    // ── Nextcloud / WebDAV (Fase 3) ────────────────────────

    public function test_webdav_provider_is_accepted(): void
    {
        $this->set(['storage_provider' => 'webdav']);

        $this->assertSame('webdav', app(StorageManager::class)->provider());
        $this->assertStringContainsString('WebDAV', app(StorageManager::class)->label());
    }

    public function test_webdav_disk_config_is_assembled_from_settings(): void
    {
        $this->set([
            'storage_provider'        => 'webdav',
            'storage_webdav_base_uri' => 'https://cloud.contoh.com/remote.php/dav/files/hris/',
            'storage_webdav_username' => 'hris',
            'storage_webdav_password' => 'app-pass',
            'storage_webdav_prefix'   => 'HRIS',
        ]);

        $config = app(StorageManager::class)->diskConfig();

        $this->assertSame('webdav', $config['driver']);
        $this->assertSame('https://cloud.contoh.com/remote.php/dav/files/hris/', $config['baseUri']);
        $this->assertSame('hris', $config['userName']);
        $this->assertSame('app-pass', $config['password']);
        $this->assertSame('HRIS', $config['prefix']);
    }

    public function test_webdav_driver_is_registered_and_builds_a_disk(): void
    {
        $disk = Storage::build([
            'driver' => 'webdav', 'baseUri' => 'https://cloud.contoh.com/remote.php/dav/files/u/',
            'userName' => 'u', 'password' => 'p', 'prefix' => '',
        ]);

        $this->assertInstanceOf(\Illuminate\Filesystem\FilesystemAdapter::class, $disk);
    }

    public function test_webdav_password_is_encrypted_at_rest(): void
    {
        $this->set(['storage_webdav_password' => 'super-app-pass']);

        $raw = \App\Models\Setting::where('key', 'storage_webdav_password')->value('value');

        $this->assertNotSame('super-app-pass', $raw);
        $this->assertSame('super-app-pass', setting('storage_webdav_password'));
    }
}
