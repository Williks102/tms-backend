<?php
// ══════════════════════════════════════════════════════════════════════════
// app/Services/Security/TurnstileService.php
//
// Vérifie un jeton Cloudflare Turnstile (CAPTCHA invisible) — voir
// MyPurchasesController::index(), seul appelant à ce jour. Empêche
// l'énumération automatisée de numéros de téléphone sur /mes-achats sans
// dépendre d'un fournisseur SMS/email (voir correctif.md point 7 — option
// retenue après que l'OTP SMS a été écartée, aucun fournisseur SMS n'est
// configuré dans ce projet).
//
// ⚠️ Si TURNSTILE_SECRET_KEY n'est pas configuré, le comportement dépend de
// l'environnement (voir revue du 12/07/2026 — le repli inconditionnel
// initial était fail-open même en prod, un déploiement sans la variable
// désactivait silencieusement toute la protection) :
//   - hors production (dev local avant création d'un compte Cloudflare) :
//     vérification court-circuitée en succès, sinon /mes-achats serait
//     inutilisable tant que les clés ne sont pas générées.
//   - en production : échoue fermé (rejet), avec un Log::error explicite —
//     une prod mal configurée doit casser bruyamment, pas désactiver la
//     protection en silence.
// Le frontend applique le repli dev équivalent (voir TurnstileWidget.tsx :
// ne rend rien si NEXT_PUBLIC_TURNSTILE_SITE_KEY est absent).
// ══════════════════════════════════════════════════════════════════════════
namespace App\Services\Security;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TurnstileService
{
    private const VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    public function verify(?string $token): bool
    {
        $secret = config('services.turnstile.secret_key');

        if (!$secret) {
            if (app()->environment('production')) {
                Log::error('Turnstile: TURNSTILE_SECRET_KEY absent en production — vérification refusée (échec fermé).');

                return false;
            }

            Log::warning('Turnstile non configuré (TURNSTILE_SECRET_KEY absent) — vérification court-circuitée (dev uniquement).');

            return true;
        }

        if (!$token) {
            return false;
        }

        try {
            // Pas de timeout par défaut du client HTTP Laravel (30s) — chaque
            // requête /mes-achats tiendrait un worker PHP jusqu'à 30s si
            // Cloudflare est lent/injoignable. Borné volontairement court :
            // une vérification qui prend plus de quelques secondes est de
            // toute façon inutilisable en pratique.
            //
            // 'remoteip' volontairement omis (paramètre optionnel côté
            // Cloudflare) : $request->ip() reflète l'IP du proxy d'hébergement
            // tant qu'aucun trustProxies n'est configuré (Railway et
            // équivalents) — l'envoyer risquerait de faire rejeter des jetons
            // légitimes résolus depuis une IP différente de celle vue par
            // Laravel, sans aucun bénéfice de sécurité en retour (Cloudflare
            // valide déjà le jeton lui-même).
            $response = Http::asForm()->timeout(5)->connectTimeout(3)->post(self::VERIFY_URL, [
                'secret'   => $secret,
                'response' => $token,
            ]);

            return (bool) $response->json('success');
        } catch (\Throwable $e) {
            Log::error('Turnstile: échec de la vérification', ['error' => $e->getMessage()]);

            return false;
        }
    }
}
