<?php
// ══════════════════════════════════════════════════════════════════════════
// app/Services/Payments/PaiementProService.php
// Client SOAP pour l'API OnlineServicePayment_v2 de PaiementPro (paiement en
// ligne des billets, voir Services/Tickets/TicketService::purchaseOnline()).
//
// ⚠️ C'est la méthode officiellement documentée (voir OnlinePayment_v1.3_OK.pdf
// à la racine du repo — fonction SOAP initTransact). Un endpoint JSON REST
// alternatif (webservice/onlinepayment/init/curl-init.php) existe et a été
// utilisé dans une première version de cette intégration — confirmé par
// PaiementPro que ce chemin JSON est un mode test qui ne débite jamais
// réellement, seul SOAP est valable pour un vrai paiement. Ne jamais revenir
// au JSON pour la logique de paiement réelle.
//
// Pas d'hôte sandbox distinct : le WSDL n'existe que sur www.paiementpro.net
// (sandbox.paiementpro.net renvoie 404 sur ce endpoint) — le mode test/réel
// dépend du compte marchand (merchantId) lui-même, pas de l'URL appelée.
// ══════════════════════════════════════════════════════════════════════════
namespace App\Services\Payments;

use App\Models\Ticket;
use Illuminate\Support\Facades\Log;

class PaiementProService
{
    // XOF — code devise CountryCurrencyCode attendu par l'API PaiementPro.
    private const CURRENCY_CODE_XOF = '952';

    // Canaux confirmés fonctionnels via SOAP pour ce merchantId (voir
    // vérification en sandbox — le PDF officiel liste OMCIV2/MOMO/CARD/FLOOZ/
    // PAYPAL, daté 2021, incomplet pour les opérateurs CI actuels).
    public const CHANNELS = ['CARD', 'OMCIV2', 'MOMOCI', 'WAVECI', 'MOOVCI'];

    private const RESPONSE_CODE_LABELS = [
        '10' => 'Paramètres insuffisants',
        '11' => 'ID marchand inconnu',
        '-1' => 'Erreur initialisation',
    ];

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

        $request = [
            'merchantId'          => $this->merchantId(),
            'referenceNumber'     => $ticket->payment_token,
            'amount'               => (int) round((float) $ticket->price_fcfa),
            'channel'              => $channel,
            'countryCurrencyCode'  => self::CURRENCY_CODE_XOF,
            'customerFirstName'    => $firstName,
            'customerLastname'     => $lastName,
            'customerEmail'        => $ticket->passenger_email,
            'customerPhoneNumber'  => $this->normalizePhone($ticket->passenger_phone),
            'description'          => "Billet {$ticket->reference}",
            'notificationURL'      => rtrim(config('app.url'), '/') . '/api/v1/payments/paiementpro/notify',
            'returnURL'            => rtrim(config('services.paiementpro.frontend_url'), '/') . '/billets/retour?ref=' . $ticket->payment_token,
            'returnContext'        => json_encode(['ticket_id' => $ticket->id]),
        ];

        try {
            $soap = new \SoapClient("{$this->baseUrl()}/webservice/OnlineServicePayment_v2.php?wsdl", [
                'cache_wsdl' => WSDL_CACHE_NONE,
                'connection_timeout' => 20,
            ]);

            $response = $soap->initTransact($request);
        } catch (\SoapFault $e) {
            Log::error('PaiementPro SOAP fault', ['ticket' => $ticket->reference, 'error' => $e->getMessage()]);

            throw new \Exception('Échec de l\'initialisation du paiement PaiementPro : ' . $e->getMessage());
        }

        Log::info('PaiementPro init (SOAP)', [
            'ticket'   => $ticket->reference,
            'channel'  => $channel,
            'code'     => $response->Code ?? null,
            'description' => $response->Description ?? null,
            'session_id'  => $response->Sessionid ?? null,
        ]);

        if (($response->Code ?? null) !== '0' || empty($response->Sessionid)) {
            $label = self::RESPONSE_CODE_LABELS[$response->Code ?? ''] ?? null;
            throw new \Exception($response->Description ?? $label ?? 'Échec de l\'initialisation du paiement PaiementPro');
        }

        return [
            'url'        => "{$this->baseUrl()}/webservice/onlinepayment/processing_v2.php?sessionid={$response->Sessionid}",
            'session_id' => $response->Sessionid,
        ];
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

    /**
     * PaiementPro exige 8 à 15 chiffres, rien d'autre — /billets collecte le
     * téléphone en format libre ("+225 07 00 00 00 00"), donc espaces/+/tirets
     * sont retirés ici plutôt que de contraindre la saisie côté client.
     */
    private function normalizePhone(?string $phone): ?string
    {
        return $phone ? preg_replace('/\D+/', '', $phone) : $phone;
    }
}
