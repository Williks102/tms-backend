<?php
// ══════════════════════════════════════════════════════════════════════════
// app/Jobs/Fuel/CheckMaintenanceThresholds.php
// ══════════════════════════════════════════════════════════════════════════
namespace App\Jobs\Fuel;

use App\Models\Vehicle;
use App\Services\Fuel\MaintenanceAlertService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CheckMaintenanceThresholds implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(private readonly Vehicle $vehicle) {}

    public function handle(MaintenanceAlertService $maintenanceAlertService): void
    {
        $maintenanceAlertService->checkVehicle($this->vehicle);
    }
}
