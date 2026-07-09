<?php
// ══════════════════════════════════════════════════════════════════════════
// app/Http/Controllers/Payments/PaiementProWebhookController.php
//
// ⚠️ PaiementPro ne fournit aucune clé/signature pour authentifier ses
// notifications (confirmé — pas de mécanisme documenté). Ce endpoint est
// donc sécurisé "à la main", sans garantie cryptographique :
//   1. Le "secret" est payment_token — 40 caractères aléatoires générés par
//      nous, jamais la référence publique du billet (séquentielle). Il n'est
//      exposé qu'à PaiementPro (referenceNumber) et au navigateur du client
//      via returnURL — donc devinable seulement par quelqu'un qui a déjà
//      initié CE paiement précis, pas par un tiers au hasard.
//   2. Transition stricte : seul un billet "pending" peut passer "paid" —
//      idempotent, une notification dupliquée ou tardive ne fait rien.
//   3. Montant vérifié quand présent dans le payload — rejet si différent.
//   4. Chaque notification (acceptée ou non) est journalisée intégralement
//      via ActivityLogger — permet un contrôle a posteriori/rapprochement
//      avec le tableau de bord PaiementPro.
//   5. Filet de sécurité côté métier : un billet payé en ligne reste visible
//      et annulable par un manager sur /tickets — une fraude serait donc
//      détectable et réversible, pas un point de non-retour silencieux.
//
// Format réel observé en sandbox le 2026-07-09 (paiement CARD complet de
// bout en bout, payload journalisé intégralement) :
//   {"merchantId":"PP-F1765","sessionId":"...","payId":"PAY-...",
//    "channel":"CARD","countryCurrencyCode":"952","referenceNumber":"...",
//    "amount":5000,"transactiondt":"2026-07-09 14:28:25",
//    "customerId":"test@tms-ci.com","returnContext":"{...}","responsecode":0}
// → le signal de succès est "responsecode": 0 (pas de champ status/success
// séparé, contrairement à ce que la doc publique laissait supposer). Les
// anciens noms de champs candidats restent tolérés en repli au cas où un
// autre canal (mobile money) renvoie une forme différente — jamais observé,
// à vérifier si un jour un paiement OMCIV2/MOMOCI/WAVECI/MOOVCI échoue à se
// confirmer alors qu'il a réellement abouti.
// ══════════════════════════════════════════════════════════════════════════
namespace App\Http\Controllers\Payments;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Services\Audit\ActivityLogger;
use App\Services\Tickets\TicketService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaiementProWebhookController extends Controller
{
    public function __construct(
        private readonly TicketService $ticketService,
        private readonly ActivityLogger $activityLogger,
    ) {}

    private const SUCCESS_TOKENS = ['SUCCESS', 'SUCCESSFUL', 'APPROVED', 'PAID', 'OK', '00', true, 1, '1'];

    public function handle(Request $request): JsonResponse
    {
        $payload = $request->all();

        Log::info('Notification PaiementPro reçue', ['payload' => $payload, 'ip' => $request->ip()]);

        $reference = $payload['referenceNumber'] ?? $payload['reference'] ?? $payload['token'] ?? null;

        if (!$reference) {
            $this->reject($payload, $request->ip(), 'Aucune référence dans le payload');
            return response()->json(['success' => true]); // 200 quoi qu'il arrive — évite les retries en boucle du côté PaiementPro
        }

        $ticket = Ticket::where('payment_token', $reference)->first();

        if (!$ticket) {
            $this->reject($payload, $request->ip(), "Référence inconnue: {$reference}");
            return response()->json(['success' => true]);
        }

        $amount = $payload['amount'] ?? null;
        if ($amount !== null && (int) $amount !== (int) round((float) $ticket->price_fcfa)) {
            $this->reject($payload, $request->ip(), "Montant incohérent pour {$ticket->reference}: reçu {$amount}, attendu {$ticket->price_fcfa}", $ticket);
            return response()->json(['success' => true]);
        }

        // responsecode: 0 = succès (confirmé en sandbox réel) — les autres
        // noms de champs restent un repli, jamais observés en pratique.
        $responseCode = $payload['responsecode'] ?? null;
        $status       = $payload['status'] ?? $payload['transactionStatus'] ?? $payload['success'] ?? null;

        $isSuccess = ($responseCode !== null && (int) $responseCode === 0)
            || ($status !== null && in_array($status, self::SUCCESS_TOKENS, true));

        if (!$isSuccess) {
            // Échec/annulation signalé par PaiementPro — on ne touche pas au
            // billet (il reste "pending", le client peut retenter le paiement
            // depuis /billets/retour tant que la réservation n'a pas expiré).
            $this->activityLogger->log('payment.failed_notification', $ticket, "Notification d'échec PaiementPro pour {$ticket->reference}", metadata: ['raw_notification' => $payload]);
            return response()->json(['success' => true]);
        }

        $this->ticketService->confirmOnlinePayment($ticket, $payload);

        return response()->json(['success' => true]);
    }

    private function reject(array $payload, ?string $ip, string $reason, ?Ticket $ticket = null): void
    {
        Log::warning('Notification PaiementPro rejetée', ['reason' => $reason, 'ip' => $ip]);

        $this->activityLogger->log(
            'payment.notification_rejected',
            $ticket,
            $reason,
            metadata: ['raw_notification' => $payload, 'ip' => $ip],
        );
    }
}
