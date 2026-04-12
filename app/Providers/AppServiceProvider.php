<?php

namespace App\Providers;

use App\Models\Departure;
//use App\Models\FuelConsumptionLog;
use App\Observers\DepartureObserver;
//use App\Observers\FuelConsumptionObserver;
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
    //FuelConsumptionLog::observe(FuelConsumptionObserver::class);
    //Incident::observe(IncidentObserver::class);
    }
}