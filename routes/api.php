<?php

// ══════════════════════════════════════════════════════════════════════════
// routes/api.php — ROUTES COMPLÈTES DES 3 MODULES
// ══════════════════════════════════════════════════════════════════════════
use App\Http\Controllers\AlertController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Drivers\DriverController;
use App\Http\Controllers\Fuel\FuelVoucherController;
use App\Http\Controllers\Fuel\MaintenancePlanController;
use App\Http\Controllers\Planning\DepartureController;
use App\Http\Controllers\Planning\RouteController;
use App\Http\Controllers\Planning\ScheduleTemplateController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Incidents\IncidentController;

// Auth routes (outside sanctum middleware)
Route::post('/login',  [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');

Route::prefix('v1')->middleware(['auth:sanctum'])->group(function () {

    // ── MODULE PLANNING ───────────────────────────────────────────────
    Route::prefix('planning')->group(function () {
        Route::apiResource('routes', RouteController::class);
        Route::post('routes/{route}/stops', [RouteController::class, 'addStop']);

        Route::apiResource('templates', ScheduleTemplateController::class);
        Route::post('templates/{template}/generate', [ScheduleTemplateController::class, 'generate']);

        // IMPORTANT: routes nommées avant {departure}
        Route::get('departures/live',                    [DepartureController::class, 'live']);
        Route::get('departures',                         [DepartureController::class, 'index']);
        Route::post('departures',                        [DepartureController::class, 'store']);
        Route::get('departures/{departure}',             [DepartureController::class, 'show']);
        Route::patch('departures/{departure}/status',    [DepartureController::class, 'updateStatus']);
        Route::delete('departures/{departure}',          [DepartureController::class, 'destroy']);

        Route::get('vehicles/available',                 [DepartureController::class, 'availableVehicles']);
        Route::get('gates/available',                    [DepartureController::class, 'availableGates']);
    });

    // ── MODULE CHAUFFEURS ─────────────────────────────────────────────
    Route::prefix('drivers')->group(function () {
        Route::get('/',                                  [DriverController::class, 'index']);
        Route::post('/',                                 [DriverController::class, 'store']);
        Route::get('/available',                         [DriverController::class, 'available']);
        Route::get('/scores/monthly',                    [DriverController::class, 'monthlyScores']);
        Route::get('/{driver}',                          [DriverController::class, 'show']);
        Route::put('/{driver}',                          [DriverController::class, 'update']);
        Route::patch('/{driver}/status',                 [DriverController::class, 'updateStatus']);
        Route::get('/{driver}/rest/check',               [DriverController::class, 'checkRest']);
        Route::post('/{driver}/rest/end',                [DriverController::class, 'endRest']);
        Route::get('/{driver}/trips',                    [DriverController::class, 'trips']);
        Route::post('/{driver}/trips/{departure}/stats', [DriverController::class, 'recordTripStats']);
        Route::get('/{driver}/scores',                   [DriverController::class, 'scores']);
        Route::post('/{driver}/scores/bonus',            [DriverController::class, 'assignBonus']);
        Route::post('/{driver}/documents',               [DriverController::class, 'uploadDocument']);
        Route::get('/documents/expiring',                [DriverController::class, 'expiringDocuments']);
    });

    // ── MODULE CARBURANT & MAINTENANCE ────────────────────────────────
    Route::prefix('fuel')->group(function () {
        Route::get('/vouchers',                          [FuelVoucherController::class, 'index']);
        Route::post('/vouchers',                         [FuelVoucherController::class, 'store']);
        Route::patch('/vouchers/{voucher}/approve',      [FuelVoucherController::class, 'approve']);
        Route::patch('/vouchers/{voucher}/reject',       [FuelVoucherController::class, 'reject']);
        Route::patch('/vouchers/{voucher}/consume',      [FuelVoucherController::class, 'consume']);
        Route::post('/consumption',                      [FuelVoucherController::class, 'recordConsumption']);
        Route::get('/consumption/vehicle/{vehicle}',     [FuelVoucherController::class, 'vehicleHistory']);
        Route::get('/consumption/stats',                 [FuelVoucherController::class, 'stats']);
    });

    Route::prefix('maintenance')->group(function () {
        Route::get('/plans',                             [MaintenancePlanController::class, 'index']);
        Route::post('/plans',                            [MaintenancePlanController::class, 'store']);
        Route::get('/due',                               [MaintenancePlanController::class, 'due']);
        Route::post('/records',                          [MaintenancePlanController::class, 'record']);
        Route::get('/records/vehicle/{vehicle}',         [MaintenancePlanController::class, 'vehicleHistory']);
    });

    // ── ALERTES (partagées) ────────────────────────────────────────────
    Route::prefix('alerts')->group(function () {
        Route::get('/',                                  [AlertController::class, 'index']);
        Route::patch('/{alert}/resolve',                 [AlertController::class, 'resolve']);
        Route::get('/summary',                           [AlertController::class, 'summary']);
    });

    // ── DASHBOARD ─────────────────────────────────────────────────────
    Route::prefix('dashboard')->group(function () {
        Route::get('/live',                              [DashboardController::class, 'live']);
        Route::get('/rentabilite',                       [DashboardController::class, 'rentabilite']);
        Route::get('/weekly',                            [DashboardController::class, 'weekly']);
    });


    Route::prefix('incidents')->group(function () {
        Route::get('/quality/drivers',               [IncidentController::class, 'qualityDrivers']);
        Route::get('/quality/vehicles',              [IncidentController::class, 'qualityVehicles']);
        Route::get('/quality/routes',                [IncidentController::class, 'qualityRoutes']);
        Route::get('/stats',                         [IncidentController::class, 'stats']);
        Route::get('/',                              [IncidentController::class, 'index']);
        Route::post('/',                             [IncidentController::class, 'store']);
        Route::get('/{incident}',                    [IncidentController::class, 'show']);
        Route::patch('/{incident}/status',           [IncidentController::class, 'updateStatus']);
        Route::delete('/{incident}',                 [IncidentController::class, 'destroy']);
        Route::post('/{incident}/actions',           [IncidentController::class, 'addAction']);
        Route::get('/{incident}/actions',            [IncidentController::class, 'indexActions']);
        Route::post('/{incident}/media',             [IncidentController::class, 'uploadMedia']);
    });
});