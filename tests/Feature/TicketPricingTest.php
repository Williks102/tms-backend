<?php
// ══════════════════════════════════════════════════════════════════════════
// Runbook sécurité v3, point 2 — price_fcfa était accepté depuis la requête
// même sur le canal en ligne : un POST direct pouvait fixer un prix
// arbitraire. Corrigé en retirant la règle de validation côté public et en
// forçant le tarif serveur dans TicketService::issue() quel que soit ce que
// contient encore le payload.
// ══════════════════════════════════════════════════════════════════════════
namespace Tests\Feature;

use App\Models\Departure;
use App\Models\Ticket;
use App\Services\Payments\PaiementProService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketPricingTest extends TestCase
{
    use RefreshDatabase;

    public function test_prix_client_ignore_sur_achat_en_ligne(): void
    {
        $this->mock(PaiementProService::class, function ($mock) {
            $mock->shouldReceive('initPayment')->once()->andReturn([
                'url'        => 'https://www.paiementpro.net/webservice/onlinepayment/processing_v2.php?sessionid=test',
                'session_id' => 'SESSION-TEST',
            ]);
        });

        $departure = Departure::factory()->create([
            'seats_available' => 10,
            'status'          => 'boarding',
        ]);
        $baseFare = (float) $departure->route->base_fare;

        $response = $this->postJson('/api/v1/tickets/online', [
            'departure_id'     => $departure->id,
            'passenger_name'   => 'Client Test',
            'passenger_phone'  => '0700000000',
            'passenger_email'  => 't@t.com',
            'payment_channel'  => 'CARD',
            'price_fcfa'       => 100, // tentative de falsification
        ]);

        $response->assertCreated();

        $ticket = Ticket::first();
        $this->assertNotNull($ticket);
        $this->assertEquals($baseFare, (float) $ticket->price_fcfa);
        $this->assertNotEquals(100.0, (float) $ticket->price_fcfa);
    }
}
