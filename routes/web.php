<?php

use Illuminate\Support\Facades\Route;

// API pure — pas de Blade (voir CLAUDE.md). Simple health-check, pas de vue
// (l'ancienne page welcome.blade.php par défaut de Laravel a été retirée :
// elle a provoqué un crash "headers already sent" en prod sur Railway).
Route::get('/', function () {
    return response()->json(['status' => 'ok', 'app' => 'TMS API']);
});
