<?php

namespace App\Providers;

use App\Models\Presence;
use App\Models\EmployeeSalaryAllowance;
use App\Models\SalaryRecap;
use App\Models\User;
use App\Observers\EmployeeSalaryAllowanceObserver;
use App\Observers\PresenceObserver;
use App\Observers\SalaryRecapObserver;
use App\Observers\UserObserver;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        User::observe(UserObserver::class);
        Presence::observe(PresenceObserver::class);
        SalaryRecap::observe(SalaryRecapObserver::class);
        EmployeeSalaryAllowance::observe(EmployeeSalaryAllowanceObserver::class);
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
