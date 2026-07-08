<?php

// ══════════════════════════════════════════════════════════════════════════
// routes/api.php — ROUTES COMPLÈTES DES 3 MODULES
// ══════════════════════════════════════════════════════════════════════════
use App\Http\Controllers\AlertController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BoardController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Drivers\DriverController;
use App\Http\Controllers\Fuel\FuelVoucherController;
use App\Http\Controllers\Fuel\MaintenancePlanController;
use App\Http\Controllers\Planning\BoardingGateController;
use App\Http\Controllers\Planning\DepartureController;
use App\Http\Controllers\Planning\RouteController;
use App\Http\Controllers\Planning\ScheduleTemplateController;
use App\Http\Controllers\Planning\StationController;
use App\Http\Controllers\Hr\DisciplinaryRecordController;
use App\Http\Controllers\Hr\EmployeeController;
use App\Http\Controllers\Hr\HrDashboardController;
use App\Http\Controllers\Hr\LeaveRequestController;
use App\Http\Controllers\Tickets\TicketController;
use App\Http\Controllers\Vehicles\VehicleController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Incidents\IncidentController;

// Auth routes (outside sanctum middleware)
// throttle:5,1 — 5 tentatives / minute / IP, en plus du verrou par compte dans AuthController
Route::post('/login',  [AuthController::class, 'login'])->middleware('throttle:5,1');
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');

// Achat en ligne — endpoint public (simule la billetterie web/mobile, ex: ClicBillet).
// Pas de middleware auth:sanctum : un client en ligne n'a pas de compte gestionnaire.
Route::post('/v1/tickets/online', [TicketController::class, 'storeOnline'])->middleware('throttle:20,1');

// Parcours lignes/départs pour la page publique d'achat (/billets côté frontend)
// — lecture seule, throttle plus généreux que l'achat lui-même.
Route::prefix('v1/tickets/online')->middleware('throttle:60,1')->group(function () {
    Route::get('routes',     [TicketController::class, 'publicRoutes']);
    Route::get('departures', [TicketController::class, 'publicDepartures']);
});

// Écran public "Départs / Arrivées" (panneau de gare) — pas de middleware
// auth:sanctum : affiché sur un navigateur de gare sans compte utilisateur.
// BoardDepartureResource filtre déjà les données personnelles (pas de
// chauffeur, pas de notes) — ne jamais réutiliser DepartureResource ici.
Route::prefix('v1/board')->middleware('throttle:120,1')->group(function () {
    Route::get('live',     [BoardController::class, 'live']);
    Route::get('stations', [BoardController::class, 'stations']);
});

Route::prefix('v1')->middleware(['auth:sanctum'])->group(function () {

    // ── MODULE PLANNING ───────────────────────────────────────────────
    // Lecture ouverte à tous les rôles authentifiés — écriture réservée au Manager
    Route::prefix('planning')->group(function () {
        Route::apiResource('routes', RouteController::class)->only(['index', 'show']);
        Route::apiResource('routes', RouteController::class)->only(['store', 'update', 'destroy'])->middleware('role:manager');
        Route::post('routes/{route}/stops',                [RouteController::class, 'addStop'])->middleware('role:manager');
        Route::put('routes/{route}/stops/{stop}',          [RouteController::class, 'updateStop'])->middleware('role:manager');
        Route::delete('routes/{route}/stops/{stop}',       [RouteController::class, 'deleteStop'])->middleware('role:manager');

        Route::apiResource('templates', ScheduleTemplateController::class)->only(['index', 'show']);
        Route::apiResource('templates', ScheduleTemplateController::class)->only(['store', 'update', 'destroy'])->middleware('role:manager');
        Route::post('templates/{template}/generate', [ScheduleTemplateController::class, 'generate'])->middleware('role:manager');

        // IMPORTANT: routes nommées avant {departure}
        Route::get('departures/live',                    [DepartureController::class, 'live']);
        Route::get('departures/mine/today',              [DepartureController::class, 'myToday'])->middleware('role:driver');
        Route::patch('departures/bulk-status',            [DepartureController::class, 'bulkUpdateStatus'])->middleware('role:manager');
        Route::get('departures',                         [DepartureController::class, 'index']);
        Route::post('departures',                        [DepartureController::class, 'store'])->middleware('role:manager');
        Route::get('departures/{departure}',             [DepartureController::class, 'show']);
        Route::put('departures/{departure}',             [DepartureController::class, 'update'])->middleware('role:manager');
        Route::patch('departures/{departure}/status',    [DepartureController::class, 'updateStatus'])->middleware('role:manager');
        // Libre-service chauffeur : SON propre départ uniquement, statuts departed/arrived
        // uniquement (voir ownership check dans le contrôleur) — route distincte de
        // celle du manager ci-dessus, pas une simple relaxation du middleware.
        Route::patch('departures/{departure}/my-status', [DepartureController::class, 'updateMyStatus'])->middleware('role:driver');
        Route::delete('departures/{departure}',          [DepartureController::class, 'destroy'])->middleware('role:manager');

        Route::get('vehicles/available',                 [DepartureController::class, 'availableVehicles']);

        // IMPORTANT: routes nommées avant {gate}
        Route::get('gates/available',                    [DepartureController::class, 'availableGates']);
        Route::get('gates',                               [BoardingGateController::class, 'index']);
        Route::post('gates',                              [BoardingGateController::class, 'store'])->middleware('role:manager');
        Route::put('gates/{gate}',                        [BoardingGateController::class, 'update'])->middleware('role:manager');
        Route::delete('gates/{gate}',                     [BoardingGateController::class, 'destroy'])->middleware('role:manager');

        Route::get('stations',                            [StationController::class, 'index']);
        Route::post('stations',                           [StationController::class, 'store'])->middleware('role:manager');
        Route::put('stations/{station}',                  [StationController::class, 'update'])->middleware('role:manager');
        Route::delete('stations/{station}',               [StationController::class, 'destroy'])->middleware('role:manager');
    });

    // ── MODULE VÉHICULES ──────────────────────────────────────────────
    // Pas d'affectation permanente à un chauffeur — liaison avec Carburant/Maintenance/Incidents via vehicle_id
    Route::prefix('vehicles')->group(function () {
        Route::get('/',              [VehicleController::class, 'index']);
        Route::post('/',             [VehicleController::class, 'store'])->middleware('role:manager');
        Route::get('/{vehicle}',     [VehicleController::class, 'show']);
        Route::put('/{vehicle}',     [VehicleController::class, 'update'])->middleware('role:manager');
        Route::delete('/{vehicle}',  [VehicleController::class, 'destroy'])->middleware('role:manager');
    });

    // ── MODULE RH ─────────────────────────────────────────────────────
    // Couvre tout le personnel (Users + Drivers) via relation polymorphe.
    // Réservé à manager+rh — les salaires ne doivent être visibles par personne d'autre.
    Route::prefix('hr')->middleware('role:manager,rh')->group(function () {
        Route::get('/dashboard',                [HrDashboardController::class, 'index']);
        Route::get('/employees',                [EmployeeController::class, 'index']);
        Route::get('/employees/{type}/{id}',    [EmployeeController::class, 'show']);

        Route::get('/leaves',                   [LeaveRequestController::class, 'index']);
        Route::post('/leaves',                  [LeaveRequestController::class, 'store']);
        Route::patch('/leaves/{leave}/approve', [LeaveRequestController::class, 'approve']);
        Route::patch('/leaves/{leave}/reject',  [LeaveRequestController::class, 'reject']);

        Route::get('/disciplinary',             [DisciplinaryRecordController::class, 'index']);
        Route::post('/disciplinary',            [DisciplinaryRecordController::class, 'store']);
    });

    // Congé en libre-service — ouvert à tout utilisateur authentifié (auth:sanctum
    // hérité du groupe v1 ci-dessus), volontairement hors du groupe role:manager,rh :
    // chacun ne gère ici que SA PROPRE demande (employable = l'utilisateur connecté).
    Route::get('/leaves/mine',  [LeaveRequestController::class, 'myLeaves']);
    Route::post('/leaves/mine', [LeaveRequestController::class, 'storeMine']);

    // ── MODULE CHAUFFEURS ─────────────────────────────────────────────
    // RH gère uniquement les documents chauffeurs — le reste des écritures est réservé au Manager
    Route::prefix('drivers')->group(function () {
        Route::get('/',                                  [DriverController::class, 'index']);
        Route::post('/',                                 [DriverController::class, 'store'])->middleware('role:manager');
        Route::get('/available',                         [DriverController::class, 'available']);
        Route::get('/scores/monthly',                    [DriverController::class, 'monthlyScores']);
        // IMPORTANT: routes nommées avant {driver}
        Route::get('/documents/expiring',                [DriverController::class, 'expiringDocuments']);
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
        // Libre-service chauffeur : driver_id forcé côté serveur, ne relâche pas
        // le gate manager,dispatcher de la route ci-dessus.
        Route::post('/mine',                         [IncidentController::class, 'storeMine'])->middleware('role:driver');
        Route::get('/{incident}',                    [IncidentController::class, 'show']);
        Route::patch('/{incident}/status',           [IncidentController::class, 'updateStatus'])->middleware('role:manager');
        Route::delete('/{incident}',                 [IncidentController::class, 'destroy'])->middleware('role:manager');
        Route::post('/{incident}/actions',           [IncidentController::class, 'addAction'])->middleware('role:manager,dispatcher');
        Route::get('/{incident}/actions',            [IncidentController::class, 'indexActions']);
        Route::post('/{incident}/media',             [IncidentController::class, 'uploadMedia'])->middleware('role:manager,dispatcher');
        Route::get('/{incident}/media/{media}/download', [IncidentController::class, 'downloadMedia']);
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