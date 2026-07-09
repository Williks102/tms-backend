<?php
// ══════════════════════════════════════════════════════════════════════════
// app/Services/Payments/PaiementProService.php
// Client HTTP pour l'API WebService v2 JSON de PaiementPro (paiement en ligne
// des billets, voir Services/Tickets/TicketService::purchaseOnline()).
//
// ⚠️ Documentation publique incomplète au moment de l'écriture : le format
// exact de la notification (webhook) n'est pas confirmé. Voir
// Http/Controllers/Payments/PaiementProWebhookController pour la stratégie
// défensive adoptée en conséquence (validation par tolérance de plusieurs
// noms de champs + journalisation intégrale de chaque notification reçue).
// ══════════════════════════════════════════════════════════════════════════
namespace App\Services\Payments;

use App\Models\Ticket;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PaiementProService
{
    // XOF — code devise CountryCurrencyCode attendu par l'API PaiementPro.
    private const CURRENCY_CODE_XOF = '952';

    public const CHANNELS = ['CARD', 'OMCIV2', 'MOMOCI', 'WAVECI', 'MOOVCI'];

    private function merchantId(): string
    {
        $id = config('services.paiementpro.merchant_id');

        if (!$id) {
            throw new \RuntimeException('PAIEMENTPRO_MERCHANT_ID non configuré');
        }

        return $id;
    }

    private function baseUrl(): string
    {
        return rtrim(config('services.paiementpro.base_url'), '/');
    }

    /**
     * Initialise une session de paiement pour un billet déjà créé en base
     * (statut pending, siège déjà réservé — voir TicketService::issue()).
     *
     * @return array{url: string, session_id: ?string}
     */
    public function initPayment(Ticket $ticket, string $channel): array
    {
        if (!in_array($channel, self::CHANNELS, true)) {
            throw new \InvalidArgumentException("Canal de paiement inconnu: {$channel}");
        }

        [$firstName, $lastName] = $this->splitName($ticket->passenger_name);

        $payload = [
            'merchantId'          => $this->merchantId(),
            'amount'               => (int) round((float) $ticket->price_fcfa),
            'description'          => "Billet {$ticket->reference}",
            'channel'              => $channel,
            'countryCurrencyCode'  => self::CURRENCY_CODE_XOF,
            'referenceNumber'      => $ticket->payment_token,
            'customerEmail'        => $ticket->passenger_email,
            'customerFirstName'    => $firstName,
            'customerLastname'     => $lastName,
            'customerPhoneNumber'  => $ticket->passenger_phone,
            'notificationURL'      => rtrim(config('app.url'), '/') . '/api/v1/payments/paiementpro/notify',
            'returnURL'            => rtrim(config('services.paiementpro.frontend_url'), '/') . '/billets/retour?ref=' . $ticket->payment_token,
            'returnContext'        => json_encode(['ticket_id' => $ticket->id]),
        ];

        $response = Http::timeout(20)
            ->withHeaders(['Content-Type' => 'application/json'])
            ->post("{$this->baseUrl()}/webservice/onlinepayment/init/curl-init.php", $payload);

        $data = $response->json();

        Log::info('PaiementPro init', [
            'ticket'    => $ticket->reference,
            'channel'   => $channel,
            'http_status' => $response->status(),
            'response'  => $data,
        ]);

        if (!$response->successful() || !($data['success'] ?? false) || empty($data['url'])) {
            throw new \Exception($data['message'] ?? 'Échec de l\'initialisation du paiement PaiementPro');
        }

        // sessionId voyage dans le query string de l'URL retournée
        // (?sessionid=...) — jamais un champ séparé dans la réponse JSON.
        $sessionId = null;
        $queryString = parse_url($data['url'], PHP_URL_QUERY);
        if ($queryString) {
            parse_str($queryString, $params);
            $sessionId = $params['sessionid'] ?? null;
        }

        return ['url' => $data['url'], 'session_id' => $sessionId];
    }

    /**
     * "Prénom Nom" → [prénom, nom] au mieux — PaiementPro exige les deux
     * séparément, on ne collecte qu'un nom complet côté /billets.
     */
    private function splitName(string $fullName): array
    {
        $parts = preg_split('/\s+/', trim($fullName), 2);

        return [$parts[0] ?? $fullName, $parts[1] ?? $parts[0] ?? $fullName];
    }
}
