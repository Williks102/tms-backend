<?php
// ══════════════════════════════════════════════════════════════════════════
// app/Http/Middleware/EnsureSubscriptionActive.php
//
// Coupe l'accès au back-office (routes authentifiées) si l'abonnement SaaS
// de ce déploiement client est suspendu pour non-paiement — voir la
// commande artisan tms:subscription pour activer/suspendre.
//
// Décision prise le 21/07/2026 : coupe UNIQUEMENT le back-office (routes
// sous auth:sanctum), jamais les pages publiques client final (achat de
// billet, suivi de colis, écran de gare) — un voyageur ne doit pas subir
// un litige de paiement entre nous et la compagnie de transport.
// ══════════════════════════════════════════════════════════════════════════
namespace App\Http\Middleware;

use App\Models\SubscriptionStatus;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSubscriptionActive
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!SubscriptionStatus::current()->isActive()) {
            return response()->json([
                'message' => 'Compte suspendu — abonnement impayé. Contactez le support pour réactiver l\'accès.',
            ], 402);
        }

        return $next($request);
    }
}
