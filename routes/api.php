<?php

// ══════════════════════════════════════════════════════════════════════════
// routes/api.php — ROUTES COMPLÈTES DES 3 MODULES
// ══════════════════════════════════════════════════════════════════════════
use App\Http\Controllers\Accounting\AccountController;
use App\Http\Controllers\Accounting\CashVoucherController;
use App\Http\Controllers\Accounting\JournalEntryController;
use App\Http\Controllers\Accounting\PayrollController;
use App\Http\Controllers\Accounting\PayrollTaxBracketController;
use App\Http\Controllers\Accounting\ReportController;
use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\AlertController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BoardController;
use App\Http\Controllers\Colis\ParcelController;
use App\Http\Controllers\Fret\FreightClientController;
use App\Http\Controllers\Fret\FreightPricingSettingController;
use App\Http\Controllers\Fret\FreightShipmentController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MyPurchasesController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\Payments\PaiementProWebhookController;
use App\Http\Controllers\SuperAdmin\SuperAdminController;
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
    // /billets/retour interroge ce point après redirection PaiementPro —
    // IMPORTANT: route nommée avant toute future {ticket} paramétrée sur ce préfixe.
    Route::get('status',     [TicketController::class, 'onlineStatus']);
});

// Webhook PaiementPro (serveur-à-serveur, pas un navigateur) — voir
// PaiementProWebhookController pour la stratégie de sécurisation en
// l'absence de signature fournie par PaiementPro. Throttle généreux :
// c'est un appel serveur légitime, pas un visiteur.
Route::post('v1/payments/paiementpro/notify', [PaiementProWebhookController::class, 'handle'])->middleware('throttle:120,1');

// Écran public "Départs / Arrivées" (panneau de gare) — pas de middleware
// auth:sanctum : affiché sur un navigateur de gare sans compte utilisateur.
// BoardDepartureResource filtre déjà les données personnelles (pas de
// chauffeur, pas de notes) — ne jamais réutiliser DepartureResource ici.
Route::prefix('v1/board')->middleware('throttle:120,1')->group(function () {
    Route::get('live',     [BoardController::class, 'live']);
    Route::get('stations', [BoardController::class, 'stations']);
});

// Suivi de colis — pas de middleware auth:sanctum : un client final n'a pas
// de compte gestionnaire. ParcelTrackResource filtre déjà pickup_code et les
// téléphones complets — ne jamais réutiliser le contrôleur authentifié ici.
Route::prefix('v1/colis/track')->middleware('throttle:60,1')->group(function () {
    Route::get('/{tracking_number}', [ParcelController::class, 'publicTrack']);
});

// Compte client léger (billets + colis) — pas de mot de passe, le numéro de
// téléphone est le seul "secret", même principe que /colis/track ci-dessus.
// Throttle durci (correctif.md point 7) : un numéro ivoirien a peu d'entropie
// (10 chiffres, préfixes opérateurs connus) comparé à un tracking_number
// aléatoire — 5/min réduit fortement le débit d'énumération. Mitigation
// partielle seule (une vérification par OTP SMS serait la solution complète,
// mais aucun fournisseur SMS n'est configuré dans ce projet actuellement).
Route::get('v1/mes-achats', [MyPurchasesController::class, 'index'])->middleware('throttle:5,1');

Route::prefix('v1')->middleware(['auth:sanctum', 'subscription.active'])->group(function () {

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
        // IMPORTANT: route nommée avant {vehicle}
        Route::get('/export',        [VehicleController::class, 'export'])->middleware('role:manager,dispatcher');
        Route::get('/{vehicle}',     [VehicleController::class, 'show']);
        Route::put('/{vehicle}',     [VehicleController::class, 'update'])->middleware('role:manager');
        Route::delete('/{vehicle}',  [VehicleController::class, 'destroy'])->middleware('role:manager');
        Route::post('/{vehicle}/documents',               [VehicleController::class, 'uploadDocument'])->middleware('role:manager');
        Route::get('/{vehicle}/documents/{document}/download', [VehicleController::class, 'downloadDocument'])->middleware('role:manager');
    });

    // ── MODULE RH ─────────────────────────────────────────────────────
    // Couvre tout le personnel (Users + Drivers) via relation polymorphe.
    // Réservé à manager+rh — les salaires ne doivent être visibles par personne d'autre.
    Route::prefix('hr')->middleware('role:manager,rh')->group(function () {
        Route::get('/dashboard',                [HrDashboardController::class, 'index']);
        // IMPORTANT: routes nommées avant {type}/{id}
        Route::get('/employees',                [EmployeeController::class, 'index']);
        Route::post('/employees',               [EmployeeController::class, 'store']);
        Route::post('/employees/import',        [EmployeeController::class, 'import']);
        Route::get('/employees/export',         [EmployeeController::class, 'export']);
        Route::get('/employees/{type}/{id}',    [EmployeeController::class, 'show']);

        Route::get('/leaves',                   [LeaveRequestController::class, 'index']);
        Route::post('/leaves',                  [LeaveRequestController::class, 'store']);
        Route::patch('/leaves/{leave}/approve', [LeaveRequestController::class, 'approve']);
        Route::patch('/leaves/{leave}/reject',  [LeaveRequestController::class, 'reject']);

        Route::get('/disciplinary',             [DisciplinaryRecordController::class, 'index']);
        Route::post('/disciplinary',            [DisciplinaryRecordController::class, 'store']);
    });

    // ── MODULE COMPTABILITÉ (OHADA / SYSCOHADA révisé) ─────────────────
    // Même convention que le module RH : middleware de rôle unique sur tout
    // le groupe, pas de sur-restriction route-par-route.
    Route::prefix('comptabilite')->middleware('role:manager,rh,comptable')->group(function () {
        Route::get('/accounts',                    [AccountController::class, 'index']);
        Route::post('/accounts',                   [AccountController::class, 'store']);

        // IMPORTANT: route nommée avant journals/{entry}
        Route::post('/journals/manual',             [JournalEntryController::class, 'storeManual']);
        Route::get('/journals',                     [JournalEntryController::class, 'index']);
        Route::get('/journals/{entry}',              [JournalEntryController::class, 'show']);

        Route::get('/grand-livre/{account}',         [ReportController::class, 'ledger']);
        Route::get('/balance',                       [ReportController::class, 'balance']);
        Route::get('/balance/export',                [ReportController::class, 'exportBalance']);
        Route::get('/compte-resultat',               [ReportController::class, 'incomeStatement']);
        Route::get('/bilan',                         [ReportController::class, 'balanceSheet']);

        Route::get('/cash-vouchers',                 [CashVoucherController::class, 'index']);
        Route::post('/cash-vouchers',                [CashVoucherController::class, 'store']);
        Route::patch('/cash-vouchers/{voucher}/approve', [CashVoucherController::class, 'approve']);
        Route::patch('/cash-vouchers/{voucher}/reject',  [CashVoucherController::class, 'reject']);

        // IMPORTANT: route nommée avant payslips/{payslip}
        Route::post('/payslips/generate',            [PayrollController::class, 'generate']);
        Route::get('/payslips',                      [PayrollController::class, 'index']);
        Route::put('/payslips/{payslip}/lines',      [PayrollController::class, 'updateLines']);
        Route::patch('/payslips/{payslip}/validate', [PayrollController::class, 'validatePayslip']);
        Route::patch('/payslips/{payslip}/pay',      [PayrollController::class, 'pay']);

        // Barème CNPS/ITS — éditable, voir avertissement dans PayrollTaxBracketSeeder
        Route::get('/payroll-tax-brackets',              [PayrollTaxBracketController::class, 'index']);
        Route::post('/payroll-tax-brackets',             [PayrollTaxBracketController::class, 'store']);
        Route::put('/payroll-tax-brackets/{bracket}',    [PayrollTaxBracketController::class, 'update']);
        Route::delete('/payroll-tax-brackets/{bracket}', [PayrollTaxBracketController::class, 'destroy']);
    });

    // ── MODULE COLIS (courrier / expédition) ───────────────────────────
    Route::prefix('colis')->middleware('role:manager,agent_colis')->group(function () {
        // IMPORTANT: routes nommées avant {parcel}
        Route::post('/quote',                [ParcelController::class, 'quote']);
        Route::post('/scan-arrival',         [ParcelController::class, 'scanArrival']);
        Route::get('/',                      [ParcelController::class, 'index']);
        Route::post('/',                     [ParcelController::class, 'store']);
        Route::get('/{parcel}',              [ParcelController::class, 'show']);
        Route::post('/{parcel}/release',     [ParcelController::class, 'release']);
        Route::patch('/{parcel}/cancel',     [ParcelController::class, 'cancel']);
        Route::patch('/{parcel}/exception',  [ParcelController::class, 'markException'])->middleware('role:manager');
        Route::post('/{parcel}/media',       [ParcelController::class, 'uploadMedia']);
        Route::get('/{parcel}/media/{media}/download', [ParcelController::class, 'downloadMedia']);
    });

    // ── MODULE FRET (camions/expéditions dédiés, clients compte entreprise) ──
    Route::prefix('fret')->middleware('role:manager,agent_fret')->group(function () {
        // IMPORTANT: routes nommées avant {shipment}
        Route::post('/quote',                        [FreightShipmentController::class, 'quote']);
        Route::get('/pricing-settings',               [FreightPricingSettingController::class, 'index']);
        Route::put('/pricing-settings',                [FreightPricingSettingController::class, 'update'])->middleware('role:manager');
        Route::get('/clients',                        [FreightClientController::class, 'index']);
        Route::post('/clients',                        [FreightClientController::class, 'store']);
        Route::get('/clients/{client}',                [FreightClientController::class, 'show']);
        Route::get('/shipments',                       [FreightShipmentController::class, 'index']);
        Route::post('/shipments',                       [FreightShipmentController::class, 'store']);
        Route::get('/shipments/{shipment}',             [FreightShipmentController::class, 'show']);
        Route::patch('/shipments/{shipment}/assign',    [FreightShipmentController::class, 'assign']);
        Route::patch('/shipments/{shipment}/status',    [FreightShipmentController::class, 'updateStatus']);
        Route::patch('/shipments/{shipment}/cancel',    [FreightShipmentController::class, 'cancel']);
        Route::post('/shipments/{shipment}/pay',        [FreightShipmentController::class, 'markPaid']);
    });

    // Congé en libre-service — ouvert à tout utilisateur authentifié (auth:sanctum
    // hérité du groupe v1 ci-dessus), volontairement hors du groupe role:manager,rh :
    // chacun ne gère ici que SA PROPRE demande (employable = l'utilisateur connecté).
    Route::get('/leaves/mine',  [LeaveRequestController::class, 'myLeaves']);
    Route::post('/leaves/mine', [LeaveRequestController::class, 'storeMine']);

    // Idem pour les bulletins de paie — libre-service chauffeur, hors du
    // groupe role:manager,rh,comptable du reste du module comptabilité.
    Route::get('/comptabilite/payslips/mine', [PayrollController::class, 'mine'])->middleware('role:driver');

    // ── NOTIFICATIONS ─────────────────────────────────────────────────
    // Ouvert à tout utilisateur authentifié, scopé au sien uniquement.
    Route::prefix('notifications')->group(function () {
        Route::get('/',                [NotificationController::class, 'index']);
        Route::get('/unread-count',    [NotificationController::class, 'unreadCount']);
        Route::patch('/read-all',      [NotificationController::class, 'markAllRead']);
        Route::patch('/{notification}/read', [NotificationController::class, 'markRead']);
    });

    // ── PISTE D'AUDIT ─────────────────────────────────────────────────
    Route::get('/audit', [ActivityLogController::class, 'index'])->middleware('role:manager,dg');

    // ── MODULE CHAUFFEURS ─────────────────────────────────────────────
    // RH gère uniquement les documents chauffeurs — le reste des écritures est réservé au Manager
    Route::prefix('drivers')->group(function () {
        Route::get('/',                                  [DriverController::class, 'index']);
        Route::post('/',                                 [DriverController::class, 'store'])->middleware('role:manager');
        Route::get('/available',                         [DriverController::class, 'available']);
        Route::get('/scores/monthly',                    [DriverController::class, 'monthlyScores']);
        // IMPORTANT: routes nommées avant {driver}
        Route::get('/documents/expiring',                [DriverController::class, 'expiringDocuments']);
        Route::get('/mine/scores',                       [DriverController::class, 'myScores'])->middleware('role:driver');
        Route::get('/export',                            [DriverController::class, 'export'])->middleware('role:manager,rh');
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
        Route::get('/consumption',                       [FuelVoucherController::class, 'consumptionIndex']);
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
    // Caissier vend au guichet — embarquement désormais réservé au Contrôleur
    // (scan, voir routes/scan ci-dessous) — le reste réservé au Manager
    Route::prefix('tickets')->group(function () {
        // IMPORTANT: routes nommées avant {ticket}
        Route::get('/stats',                              [TicketController::class, 'stats']);
        Route::get('/mine',                               [TicketController::class, 'mine'])->middleware('role:caissier');
        Route::get('/scan/stats',                         [TicketController::class, 'scanStats'])->middleware('role:manager,controleur');
        Route::post('/scan',                              [TicketController::class, 'scan'])->middleware('role:manager,controleur');
        Route::get('/departure/{departure}/manifest',      [TicketController::class, 'manifest']);
        Route::get('/',                                    [TicketController::class, 'index']);
        Route::post('/',                                   [TicketController::class, 'store'])->middleware('role:manager,caissier');
        Route::get('/{ticket}',                            [TicketController::class, 'show']);
        Route::patch('/{ticket}/status',                   [TicketController::class, 'updateStatus'])->middleware('role:manager,caissier');
    });
});

// ── SUPER ADMIN (revente SaaS) ──────────────────────────────────────────
// Volontairement HORS du groupe subscription.active ci-dessus : le super
// admin doit toujours pouvoir réactiver un abonnement suspendu, même quand
// il l'est. Role::SUPER_ADMIN n'est jamais assignable via la création de
// personnel (EmployeeController) ni seedé automatiquement pour un client —
// voir tms:create-super-admin et CLAUDE.md § Revente SaaS.
Route::prefix('v1/super-admin')->middleware(['auth:sanctum', 'role:super_admin'])->group(function () {
    Route::get('/tiers',            [SuperAdminController::class, 'tiers']);
    Route::get('/billing-report',   [SuperAdminController::class, 'billingReport']);
    Route::get('/subscription',     [SuperAdminController::class, 'subscription']);
    Route::patch('/subscription',   [SuperAdminController::class, 'updateSubscription']);
});