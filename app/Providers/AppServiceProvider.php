<?php

namespace App\Providers;

use App\Models\Departure;
use App\Models\FuelConsumptionLog;
use App\Models\FuelVoucher;
use App\Models\MaintenanceRecord;
use App\Models\Ticket;
use App\Observers\DepartureObserver;
use App\Observers\FuelConsumptionObserver;
use App\Observers\FuelVoucherObserver;
use App\Observers\MaintenanceRecordObserver;
use App\Observers\TicketObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Departure::observe(DepartureObserver::class);
        Ticket::observe(TicketObserver::class);
        FuelConsumptionLog::observe(FuelConsumptionObserver::class);
        FuelVoucher::observe(FuelVoucherObserver::class);
        MaintenanceRecord::observe(MaintenanceRecordObserver::class);
    //Incident::observe(IncidentObserver::class);
    }
}