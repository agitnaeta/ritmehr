<?php

namespace App\Providers;

use App\Services\Acc\Acc;
use App\Services\ApprovalService;
use App\Services\LeaveService;
use App\Services\NotificationService;
use App\Services\Notifications\FonnteWhatsAppGateway;
use App\Services\Notifications\LogWhatsAppGateway;
use App\Services\Notifications\WhatsAppGateway;
use App\Services\PresenceService;
use App\Services\SalaryService;
use App\Services\TransactionService;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use Opcodes\LogViewer\Facades\LogViewer;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(TransactionService::class, function ($app) {
            return new TransactionService(new Acc());
        });

        $this->app->singleton(PresenceService::class, function ($app) {
            return new PresenceService();
        });

        $this->app->singleton(SalaryService::class, function ($app) {
            return new SalaryService(
                $app->make(PresenceService::class),
                $app->make(TransactionService::class)
            );
        });

        // WhatsApp stays a no-op (logged) until a provider token is configured,
        // so notifications work out of the box without pretending to send.
        $this->app->singleton(WhatsAppGateway::class, function () {
            $token = config('services.fonnte.token');

            return $token
                ? new FonnteWhatsAppGateway($token)
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
        Blade::directive('rupiah', function ( $expression ) {
            return "Rp. <?php echo number_format($expression,0,',','.'); ?>";
        });


        LogViewer::auth(function ($request) {
            if(backpack_auth()->user()){
                return true;
            }
            return false;
        });
    }
}
