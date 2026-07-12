<?php

use Illuminate\Support\Facades\Route;

// API pure — pas de Blade (voir CLAUDE.md). Simple health-check, pas de vue
// (l'ancienne page welcome.blade.php par défaut de Laravel a été retirée :
// elle a provoqué un crash "headers already sent" en prod sur Railway).
Route::get('/', function () {
    return response()->json(['status' => 'ok', 'app' => 'TMS API']);
});

// Filet de sécurité — voir bootstrap/app.php::shouldRenderJsonWhen() et
// correctif.md point 4. Le handler d'exceptions par défaut de Laravel appelle
// `route('login')` en repli inconditionnel pour toute AuthenticationException
// non-JSON (même avec shouldRenderJsonWhen(fn () => true) enregistré — ce
// callback ne suffit pas à lui seul sur certains chemins d'exception internes,
// ex. levés depuis un middleware avant résolution complète de la requête).
// Sans route nommée 'login', ce repli lève une RouteNotFoundException → 500
// au lieu du 401 attendu. Cette route ne sert JAMAIS en pratique (le frontend
// envoie toujours Accept: application/json, voir lib/api.ts) — c'est un
// filet, pas un vrai point d'entrée.
Route::get('/login', function () {
    return response()->json(['message' => 'Non authentifié.'], 401);
})->name('login');
