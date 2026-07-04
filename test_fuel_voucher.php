<?php
// test_fuel_voucher.php — Debug script pour vérifier les données et l'erreur 422

require 'vendor/autoload.php';
require 'bootstrap/app.php';

use Illuminate\Support\Facades\DB;
use App\Models\Departure;
use App\Models\Driver;
use App\Models\Vehicle;

// ──────────────────────────────────────────────────────────────────
echo "🔍 Vérification des données de test pour le bon carburant\n";
echo "──────────────────────────────────────────────────────────────────\n\n";

echo "✓ Départs existants:\n";
$departures = Departure::limit(3)->get(['id', 'route_id', 'vehicle_id', 'driver_id', 'status']);
if ($departures->isEmpty()) {
    echo "  ⚠️  AUCUN DÉPART! Lancer: php artisan migrate:fresh --seed\n";
} else {
    foreach ($departures as $d) {
        echo "  - ID {$d->id}: Vehicle {$d->vehicle_id}, Driver {$d->driver_id}, Status: {$d->status}\n";
    }
    
    // Montrer le premier départ détaillé
    echo "\n✓ Départ #1 détaillé:\n";
    $first = $departures->first();
    echo "  - Relation Route: " . ($first->route ? "✓ OK" : "✗ MISSING") . "\n";
    echo "  - Relation Vehicle: " . ($first->vehicle ? "✓ OK" : "✗ MISSING") . "\n";
    echo "  - Relation Driver: " . ($first->driver ? "✓ OK" : "✗ MISSING") . "\n";
}

echo "\n✓ Chauffeurs existants:\n";
$drivers = Driver::limit(3)->get(['id', 'first_name', 'last_name']);
if ($drivers->isEmpty()) {
    echo "  ⚠️  AUCUN CHAUFFEUR!\n";
} else {
    foreach ($drivers as $d) {
        echo "  - ID {$d->id}: {$d->first_name} {$d->last_name}\n";
    }
}

echo "\n✓ Véhicules existants:\n";
$vehicles = Vehicle::limit(3)->get(['id', 'plate_number', 'fuel_consumption_per_100km']);
if ($vehicles->isEmpty()) {
    echo "  ⚠️  AUCUN VÉHICULE!\n";
} else {
    foreach ($vehicles as $v) {
        echo "  - ID {$v->id}: {$v->plate_number} ({$v->fuel_consumption_per_100km} L/100km)\n";
    }
}

echo "\n──────────────────────────────────────────────────────────────────\n";
echo "📋 Exemple de requête POST /fuel/vouchers valide:\n";
echo "──────────────────────────────────────────────────────────────────\n\n";

if (!$departures->isEmpty() && !$drivers->isEmpty() && !$vehicles->isEmpty()) {
    $dep = $departures->first();
    $drv = $drivers->first();
    $veh = $vehicles->first();
    
    $payload = [
        'departure_id'     => $dep->id,
        'driver_id'        => $drv->id,
        'vehicle_id'       => $veh->id,
        'requested_liters' => 50,
        'fuel_type'        => 'gasoil',
        'price_per_liter'  => 850,
        'station_name'     => 'Station Marcory'
    ];
    
    echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n\n";
    
    echo "📌 Commande curl pour tester:\n";
    echo "TOKEN=\$(curl -s -X POST http://localhost:8000/api/login \\\n";
    echo "  -H \"Content-Type: application/json\" \\\n";
    echo "  -d '{\"email\":\"manager@tms-ci.com\",\"password\":\"password\"}' \\\n";
    echo "  | grep -o '\"token\":\"[^\"]*\"' | cut -d'\"' -f4)\n\n";
    
    echo "curl -X POST http://localhost:8000/api/v1/fuel/vouchers \\\n";
    echo "  -H \"Authorization: Bearer \$TOKEN\" \\\n";
    echo "  -H \"Content-Type: application/json\" \\\n";
    echo "  -d '" . json_encode($payload) . "'\n";
} else {
    echo "⚠️  Données insuffisantes. Exécuter:\n";
    echo "   php artisan migrate:fresh --seed\n";
}

echo "\n";
