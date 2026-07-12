<?php
// ══════════════════════════════════════════════════════════════════════════
// app/Http/Controllers/AuthController.php
//
// Authentification hybride — voir correctif.md point 4 :
//   - Requête "stateful" (navigateur SPA reconnu via SANCTUM_STATEFUL_DOMAINS,
//     voir bootstrap/app.php::statefulApi()) → session Laravel, cookie
//     httpOnly géré entièrement côté serveur, jamais lisible/volable en JS
//     (remplace l'ancien Bearer token renvoyé en clair et stocké en
//     localStorage, exposé à toute XSS sur la page).
//   - Toute autre requête (curl, tests manuels — voir CLAUDE.md § Commandes
//     utiles, un futur client mobile) → Bearer token Sanctum classique, seul
//     mécanisme qui fonctionne sans navigateur pour porter des cookies.
//
// $request->hasSession() est le signal fiable pour distinguer les deux : il
// n'est vrai que si EnsureFrontendRequestsAreStateful a reconnu la requête
// comme stateful (c'est cette middleware qui démarre la session Laravel).
// ⚠️ Piège déjà rencontré : une première version de ce contrôleur affirmait
// ce comportement hybride en commentaire sans jamais appeler createToken()
// nulle part — le workflow de test curl documenté dans CLAUDE.md était donc
// silencieusement cassé. Toujours vérifier que le code fait ce que le
// commentaire prétend.
//
// Ce changement résout aussi point 10 (session unique par compte) pour le
// cas navigateur : chaque appareil a son propre cookie de session chiffré,
// se connecter sur un second appareil n'affecte plus le premier.
// ══════════════════════════════════════════════════════════════════════════
namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

class AuthController extends Controller
{
    // POST /api/login
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        // Verrou par compte (en plus du throttle IP sur la route) — empêche
        // le bruteforce d'un compte précis depuis des IP différentes.
        $key = 'login:' . strtolower($data['email']) . '|' . $request->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);
            return response()->json([
                'message' => "Trop de tentatives. Réessayez dans {$seconds}s.",
            ], 429);
        }

        $user = User::where('email', $data['email'])->first();

        if (!$user || !Hash::check($data['password'], $user->password)) {
            RateLimiter::hit($key, 60);
            return response()->json([
                'message' => 'Email ou mot de passe incorrect',
            ], 401);
        }

        RateLimiter::clear($key);

        $userPayload = [
            'id'    => $user->id,
            'name'  => $user->name,
            'email' => $user->email,
            'role'  => $user->role->value,
        ];

        if ($request->hasSession()) {
            Auth::guard('web')->login($user);
            // Nouvel identifiant de session après authentification —
            // empêche la fixation de session (un identifiant pré-login ne
            // doit jamais rester valide après élévation de privilège).
            $request->session()->regenerate();

            return response()->json(['user' => $userPayload]);
        }

        // Client non-stateful (curl, script, futur client mobile) — Bearer
        // token classique. Cap à 5 sessions actives par compte plutôt qu'une
        // suppression par nom fixe (piège déjà rencontré : ça déconnectait
        // n'importe quel autre client du même nom, jamais souhaité).
        if ($user->tokens()->count() >= 5) {
            $user->tokens()->oldest()->first()->delete();
        }

        $token = $user->createToken($request->userAgent() ?: 'api-client')->plainTextToken;

        return response()->json(['token' => $token, 'user' => $userPayload]);
    }

    // POST /api/logout
    public function logout(Request $request): JsonResponse
    {
        if ($request->hasSession() && Auth::guard('web')->check()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        } else {
            // Révoque uniquement le token courant — jamais les autres
            // sessions/appareils du même compte.
            $request->user()?->currentAccessToken()?->delete();
        }

        return response()->json(['message' => 'Déconnecté avec succès']);
    }
}
