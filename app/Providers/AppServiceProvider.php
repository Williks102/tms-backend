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
use App\Services\Notifications\LogChannel;
use App\Services\Notifications\NotificationChannel;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Canal actif = LogChannel (aucun fournisseur SMS/email configuré).
        // Brancher un vrai canal plus tard = changer cette seule ligne.
        $this->app->bind(NotificationChannel::class, LogChannel::class);
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