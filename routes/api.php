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
use App\Http\Controllers\Tickets\TicketController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Incidents\IncidentController;

// Auth routes (outside sanctum middleware)
// throttle:5,1 — 5 tentatives / minute / IP, en plus du verrou par compte dans AuthController
Route::post('/login',  [AuthController::class, 'login'])->middleware('throttle:5,1');
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');

// Achat en ligne — endpoint public (simule la billetterie web/mobile, ex: ClicBillet).
// Pas de middleware auth:sanctum : un client en ligne n'a pas de compte gestionnaire.
Route::post('/v1/tickets/online', [TicketController::class, 'storeOnline'])->middleware('throttle:20,1');

Route::prefix('v1')->middleware(['auth:sanctum'])->group(function () {

    // ── MODULE PLANNING ───────────────────────────────────────────────
    // Lecture ouverte à tous les rôles authentifiés — écriture réservée au Manager
    Route::prefix('planning')->group(function () {
        Route::apiResource('routes', RouteController::class)->only(['index', 'show']);
        Route::apiResource('routes', RouteController::class)->only(['store', 'update', 'destroy'])->middleware('role:manager');
        Route::post('routes/{route}/stops', [RouteController::class, 'addStop'])->middleware('role:manager');

        Route::apiResource('templates', ScheduleTemplateController::class)->only(['index', 'show']);
        Route::apiResource('templates', ScheduleTemplateController::class)->only(['store', 'update', 'destroy'])->middleware('role:manager');
        Route::post('templates/{template}/generate', [ScheduleTemplateController::class, 'generate'])->middleware('role:manager');

        // IMPORTANT: routes nommées avant {departure}
        Route::get('departures/live',                    [DepartureController::class, 'live']);
        Route::get('departures',                         [DepartureController::class, 'index']);
        Route::post('departures',                        [DepartureController::class, 'store'])->middleware('role:manager');
        Route::get('departures/{departure}',             [DepartureController::class, 'show']);
        Route::patch('departures/{departure}/status',    [DepartureController::class, 'updateStatus'])->middleware('role:manager');
        Route::delete('departures/{departure}',          [DepartureController::class, 'destroy'])->middleware('role:manager');

        Route::get('vehicles/available',                 [DepartureController::class, 'availableVehicles']);
        Route::get('gates/available',                    [DepartureController::class, 'availableGates']);
    });

    // ── MODULE CHAUFFEURS ─────────────────────────────────────────────
    // RH gère uniquement les documents chauffeurs — le reste des écritures est réservé au Manager
    Route::prefix('drivers')->group(function () {
        Route::get('/',                                  [DriverController::class, 'index']);
        Route::post('/',                                 [DriverController::class, 'store'])->middleware('role:manager');
        Route::get('/available',                         [DriverController::class, 'available']);
        Route::get('/scores/monthly',                    [DriverController::class, 'monthlyScores']);
        Route::get('/{driver}',                          [DriverController::class, 'show']);
        Route::put('/{driver}',                          [DriverController::class, 'update'])->middleware('role:manager');
        Route::patch('/{driver}/status',                 [DriverController::class, 'updateStatus'])->middleware('role:manager');
        Route::get('/{driver}/rest/check',               [DriverController::class, 'checkRest']);
        Route::post('/{driver}/rest/end',                [DriverController::class, 'endRest'])->middleware('role:manager');
        Route::get('/{driver}/trips',                    [DriverController::class, 'trips']);
        Route::post('/{driver}/trips/{departure}/stats', [DriverController::class, 'recordTripStats'])->middleware('role:manager');
        Route::get('/{driver}/scores',                   [DriverController::class, 'scores']);
        Route::post('/{driver}/scores/bonus',            [DriverController::class, 'assignBonus'])->middleware('role:manager');
        Route::post('/{driver}/documents',               [DriverController::class, 'uploadDocument'])->middleware('role:manager,rh');
        Route::get('/{driver}/documents/{document}/download', [DriverController::class, 'downloadDocument'])->middleware('role:manager,rh');
        Route::get('/documents/expiring',                [DriverController::class, 'expiringDocuments']);
    });

    // ── MODULE CARBURANT & MAINTENANCE ────────────────────────────────
    // Dispatcher enregistre la consommation réelle — le reste (bons, maintenance) est réservé au Manager
    Route::prefix('fuel')->group(function () {
        Route::get('/vouchers',                          [FuelVoucherController::class, 'index']);
        Route::post('/vouchers',                         [FuelVoucherController::class, 'store'])->middleware('role:manager');
        Route::patch('/vouchers/{voucher}/approve',      [FuelVoucherController::class, 'approve'])->middleware('role:manager');
        Route::patch('/vouchers/{voucher}/reject',       [FuelVoucherController::class, 'reject'])->middleware('role:manager');
        Route::patch('/vouchers/{voucher}/consume',      [FuelVoucherController::class, 'consume'])->middleware('role:manager');
        Route::post('/consumption',                      [FuelVoucherController::class, 'recordConsumption'])->middleware('role:manager,dispatcher');
        Route::get('/consumption/vehicle/{vehicle}',     [FuelVoucherController::class, 'vehicleHistory']);
        Route::get('/consumption/stats',                 [FuelVoucherController::class, 'stats']);
    });

    Route::prefix('maintenance')->group(function () {
        Route::get('/plans',                             [MaintenancePlanController::class, 'index']);
        Route::post('/plans',                            [MaintenancePlanController::class, 'store'])->middleware('role:manager');
        Route::get('/due',                               [MaintenancePlanController::class, 'due']);
        Route::post('/records',                          [MaintenancePlanController::class, 'record'])->middleware('role:manager');
        Route::get('/records/vehicle/{vehicle}',         [MaintenancePlanController::class, 'vehicleHistory']);
    });

    // ── ALERTES (partagées) ────────────────────────────────────────────
    Route::prefix('alerts')->group(function () {
        Route::get('/',                                  [AlertController::class, 'index']);
        Route::patch('/{alert}/resolve',                 [AlertController::class, 'resolve'])->middleware('role:manager');
        Route::get('/summary',                           [AlertController::class, 'summary']);
    });

    // ── DASHBOARD ─────────────────────────────────────────────────────
    Route::prefix('dashboard')->group(function () {
        Route::get('/live',                              [DashboardController::class, 'live']);
        Route::get('/rentabilite',                       [DashboardController::class, 'rentabilite']);
        Route::get('/weekly',                            [DashboardController::class, 'weekly']);
    });


    // Dispatcher signale les incidents et leurs actions/médias — statut et suppression réservés au Manager
    Route::prefix('incidents')->group(function () {
        Route::get('/quality/drivers',               [IncidentController::class, 'qualityDrivers']);
        Route::get('/quality/vehicles',              [IncidentController::class, 'qualityVehicles']);
        Route::get('/quality/routes',                [IncidentController::class, 'qualityRoutes']);
        Route::get('/stats',                         [IncidentController::class, 'stats']);
        Route::get('/',                              [IncidentController::class, 'index']);
        Route::post('/',                             [IncidentController::class, 'store'])->middleware('role:manager,dispatcher');
        Route::get('/{incident}',                    [IncidentController::class, 'show']);
        Route::patch('/{incident}/status',           [IncidentController::class, 'updateStatus'])->middleware('role:manager');
        Route::delete('/{incident}',                 [IncidentController::class, 'destroy'])->middleware('role:manager');
        Route::post('/{incident}/actions',           [IncidentController::class, 'addAction'])->middleware('role:manager,dispatcher');
        Route::get('/{incident}/actions',            [IncidentController::class, 'indexActions']);
        Route::post('/{incident}/media',             [IncidentController::class, 'uploadMedia'])->middleware('role:manager,dispatcher');
    });

    // ── MODULE BILLETTERIE ────────────────────────────────────────────
    // Caissier vend au guichet et gère l'embarquement — le reste réservé au Manager
    Route::prefix('tickets')->group(function () {
        Route::get('/stats',                              [TicketController::class, 'stats']);
        Route::get('/departure/{departure}/manifest',      [TicketController::class, 'manifest']);
        Route::get('/',                                    [TicketController::class, 'index']);
        Route::post('/',                                   [TicketController::class, 'store'])->middleware('role:manager,caissier');
        Route::get('/{ticket}',                            [TicketController::class, 'show']);
        Route::patch('/{ticket}/status',                   [TicketController::class, 'updateStatus'])->middleware('role:manager,caissier');
    });
});