<?php
require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$user = \App\Models\User::first();
if ($user) {
    $token = $user->createToken('test')->plainTextToken;
    echo "Token: " . $token . "\n";
} else {
    echo "No user found\n";
}