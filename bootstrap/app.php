<?php

use App\Http\Middleware\EnsureSubscriptionActive;
use App\Http\Middleware\EnsureUserHasRole;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Sanctum SPA (cookie httpOnly) — voir correctif.md point 4. Ajoute
        // EnsureFrontendRequestsAreStateful au groupe api : toute requête dont
        // l'Origin/Referer correspond à SANCTUM_STATEFUL_DOMAINS est traitée
        // en session (cookie), les autres (curl, futurs clients mobiles)
        // continuent d'utiliser un Bearer token classique — les deux
        // mécanismes coexistent sans changement ailleurs dans l'app.
        $middleware->statefulApi();

        // Railway (et la plupart des PaaS) sert l'app derrière un reverse
        // proxy — sans ça, $request->ip() renvoie l'IP interne du proxy pour
        // TOUTES les requêtes, pas celle du vrai client. Impact concret :
        // RateLimiter::hit() dans AuthController::login() (clé basée sur
        // l'IP) traiterait tous les visiteurs comme une seule IP, un échec
        // de connexion de quelqu'un d'autre pouvant bloquer tout le monde —
        // même chose pour les throttle: sur /mes-achats et /tickets/online.
        // 'at: *' est sûr ici : Railway ne laisse aucun trafic atteindre le
        // conteneur autrement que via son propre proxy géré.
        $middleware->trustProxies(at: '*');

        $middleware->alias([
            'role'                => EnsureUserHasRole::class,
            'subscription.active' => EnsureSubscriptionActive::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // API pure — pas de Blade. Sans ceci, une requête non authentifiée
        // sans header Accept: application/json explicite fait tomber le
        // handler d'exceptions par défaut sur une tentative de rendu HTML au
        // lieu du JSON attendu. Force TOUTE réponse d'erreur de cette API à
        // être du JSON, quel que soit le header Accept envoyé par le client.
        //
        // ⚠️ Piège réel rencontré : ce callback seul ne suffit PAS à éviter
        // le 500 sur ce chemin précis. `Handler::unauthenticated()` retombe
        // sur `redirect()->guest($exception->redirectTo($request) ??
        // route('login'))` dès que `shouldReturnJson()` renvoie false pour
        // CE callback à l'instant où l'exception est levée (observé : un
        // AuthenticationException levé depuis le middleware `auth:sanctum`
        // avant résolution complète de la requête ne passe pas forcément par
        // ce callback comme attendu) — et le `?? route('login')` s'exécute
        // alors même si `Authenticate::redirectUsing()`/
        // `AuthenticationException::redirectUsing()` ont été enregistrés
        // pour renvoyer null. Sans route nommée 'login', ce repli lève une
        // RouteNotFoundException → 500 au lieu du 401 attendu. Correction
        // réelle : garder ce callback ET définir une route 'login' de repli
        // (voir routes/web.php) qui ne sert jamais en pratique mais empêche
        // le crash si ce chemin est emprunté malgré tout.
        $exceptions->shouldRenderJsonWhen(fn () => true);
    })->create();
