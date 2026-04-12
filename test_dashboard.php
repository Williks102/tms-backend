<?php
// test_dashboard.php - Script de debug pour isoler le problème
require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Http\Controllers\DashboardController;
use Illuminate\Http\Request;

echo "Testing DashboardController live() method...\n";

$controller = new DashboardController();
$request = new Request();

try {
    echo "Testing live() method...\n";
    $start = microtime(true);
    $result = $controller->live();
    $time = microtime(true) - $start;
    echo "✓ live() completed in {$time}s\n";
    echo "Response status: " . $result->getStatusCode() . "\n";
    $data = json_decode($result->getContent(), true);
    echo "Data keys: " . implode(', ', array_keys($data)) . "\n";
} catch (Exception $e) {
    echo "✗ live() failed: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "Test completed.\n";