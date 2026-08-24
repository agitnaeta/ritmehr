<?php

namespace App\Observers;

use App\Models\Presence;
use App\Services\PresenceService;
use App\Services\SalaryService;

class PresenceObserver
{
    protected $presenceService;
    protected $salaryService;

    public function __construct(PresenceService $presenceService, SalaryService $salaryService)
    {
        $this->presenceService = $presenceService;
        $this->salaryService = $salaryService;
    }

    /**
     * Handle the Presence "created" event.
     */
    public function created(Presence $presence): void
    {
        // Calculate Late
        $this->presenceService->calculateLate($presence);
        $this->presenceService->calculateOvertime($presence);
        $this->presenceService->calculateExtraTime($presence);
        // `outside` defaults to 1 in the schema, so a row inserted with its
        // coordinates already attached (import, API, seeder) would stay flagged
        // as off-site forever if the geofence were only evaluated on update.
        $this->presenceService->recalCulateCoordinate($presence);
        $this->salaryService->recap($presence);
    }


    /**
     * Handle the Presence "updated" event.
     */
    public function updated(Presence $presence): void
    {
        // Calculate Late
        $this->presenceService->calculateLate($presence);
        $this->presenceService->calculateOvertime($presence);
        $this->presenceService->calculateExtraTime($presence);
        $this->presenceService->recalCulateCoordinate($presence);
        $this->salaryService->recap($presence);
    }

    /**
     * Handle the Presence "deleted" event.
     */
    public function deleted(Presence $presence): void
    {
        //
    }

    /**
     * Handle the Presence "restored" event.
     */
    public function restored(Presence $presence): void
    {
        //
    }

    /**
     * Handle the Presence "force deleted" event.
     */
    public function forceDeleted(Presence $presence): void
    {
        //
    }
}
