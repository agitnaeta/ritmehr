<?php

namespace App\Providers;

use App\Services\Acc\Acc;
use App\Services\ApprovalService;
use App\Services\LeaveService;
use App\Services\NotificationService;
use App\Services\Notifications\LogWhatsAppGateway;
use App\Services\Notifications\WahaWhatsAppGateway;
use App\Services\Notifications\WhatsAppGateway;
use App\Services\PresenceService;
use App\Services\SalaryService;
use App\Services\TransactionService;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;
use Opcodes\LogViewer\Facades\LogViewer;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // M14: central currency formatting (reads default_currency from M15).
        $this->app->singleton(\App\Services\CurrencyService::class, function ($app) {
            return new \App\Services\CurrencyService();
        });

        // M12: choose the ledger backend from the platform setting. Default is
        // the internal double-entry ledger; 'firefly' keeps the legacy external
        // integration for anyone who still wants it.
        $this->app->singleton(\App\Services\Acc\LedgerInterface::class, function ($app) {
            $mode = function_exists('setting') ? setting('acc_mode', 'internal') : 'internal';

            return $mode === 'firefly'
                ? new \App\Services\Acc\Acc()
                : new \App\Services\Acc\InternalLedger();
        });

        $this->app->singleton(TransactionService::class, function ($app) {
            return new TransactionService($app->make(\App\Services\Acc\LedgerInterface::class));
        });

        $this->app->singleton(PresenceService::class, function ($app) {
            return new PresenceService();
        });

        $this->app->singleton(SalaryService::class, function ($app) {
            return new SalaryService(
                $app->make(PresenceService::class),
                $app->make(TransactionService::class),
                $app->make(\App\Services\TaxService::class)
            );
        });

        // WhatsApp gateway — WAHA (self-hosted container). Falls back to a
        // logging no-op when disabled or not yet configured, so notifications
        // never break an action. Managed from the Settings UI (M15).
        $this->app->singleton(WhatsAppGateway::class, function () {
            if (! setting('whatsapp_enabled', true)) {
                return new LogWhatsAppGateway();
            }

            $url = (string) setting('waha_url', '');

            return $url
                ? new WahaWhatsAppGateway(
                    $url,
                    (string) setting('waha_session', 'default'),
                    (string) setting('waha_api_key', '')
                )
                : new LogWhatsAppGateway();
        });

        $this->app->singleton(NotificationService::class, function ($app) {
            return new NotificationService($app->make(WhatsAppGateway::class));
        });

        $this->app->singleton(ApprovalService::class, fn () => new ApprovalService());

        $this->app->singleton(LeaveService::class, function ($app) {
            return new LeaveService(
                $app->make(PresenceService::class),
                $app->make(ApprovalService::class),
                $app->make(NotificationService::class)
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // M14: currency-aware formatting via the central CurrencyService.
        // @money($amount) is the preferred directive; @rupiah stays as a
        // backward-compatible alias but now also follows the currency setting.
        Blade::directive('money', function ($expression) {
            return "<?php echo money($expression); ?>";
        });

        Blade::directive('rupiah', function ($expression) {
            return "<?php echo money($expression); ?>";
        });


        LogViewer::auth(function ($request) {
            if(backpack_auth()->user()){
                return true;
            }
            return false;
        });

        // M16 Fase 2 — register a custom "google" filesystem driver backed by
        // Google Drive (masbug/flysystem-google-drive-ext). StorageManager builds
        // a disk with driver=google at runtime from the M15 settings.
        Storage::extend('google', function ($app, $config) {
            $client = new \Google\Client();
            $client->setClientId($config['clientId'] ?? '');
            $client->setClientSecret($config['clientSecret'] ?? '');
            $client->refreshToken($config['refreshToken'] ?? '');

            $service = new \Google\Service\Drive($client);
            $adapter = new \Masbug\Flysystem\GoogleDriveAdapter(
                $service,
                $config['folder'] ?? '/',
                ['useDisplayPaths' => true]
            );

            $filesystem = new \League\Flysystem\Filesystem($adapter);

            return new \Illuminate\Filesystem\FilesystemAdapter($filesystem, $adapter, $config);
        });

        // M16 Fase 3 — register a custom "webdav" driver (Nextcloud / ownCloud /
        // any WebDAV server). StorageManager builds a disk with driver=webdav at
        // runtime from the M15 settings.
        Storage::extend('webdav', function ($app, $config) {
            $client = new \Sabre\DAV\Client([
                'baseUri'  => $config['baseUri'] ?? '',
                'userName' => $config['userName'] ?? '',
                'password' => $config['password'] ?? '',
            ]);

            $adapter = new \League\Flysystem\WebDAV\WebDAVAdapter($client, $config['prefix'] ?? '');
            $filesystem = new \League\Flysystem\Filesystem($adapter);

            return new \Illuminate\Filesystem\FilesystemAdapter($filesystem, $adapter, $config);
        });
    }
}
