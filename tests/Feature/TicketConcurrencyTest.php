<?php
// ══════════════════════════════════════════════════════════════════════════
// Runbook sécurité v3, point 1 — la référence de billet était dérivée d'un
// count()+1 : deux ventes simultanées sur deux départs différents pouvaient
// lire le même count() (isolation READ COMMITTED) et générer la même
// référence. Corrigé en dérivant la référence de l'id auto-incrémenté
// (jamais rejoué, jamais collisionnable, quel que soit l'ordre d'exécution).
// ══════════════════════════════════════════════════════════════════════════
namespace Tests\Feature;

use App\Models\Departure;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_references_uniques_sur_plusieurs_departs(): void
    {
        $caissier = User::factory()->create(['role' => 'caissier']);

        $departures = Departure::factory()->count(2)->create([
            'seats_available' => 10,
            'status'          => 'boarding',
        ]);

        foreach ($departures as $departure) {
            foreach (range(1, 3) as $i) {
                $response = $this->actingAs($caissier)->postJson('/api/v1/tickets', [
                    'departure_id'    => $departure->id,
                    'passenger_name'  => "Passager {$i}",
                    'payment_method'  => 'cash',
                ]);

                $response->assertCreated();
            }
        }

        $this->assertSame(6, Ticket::count());
        $this->assertSame(
            Ticket::count(),
            Ticket::pluck('reference')->unique()->count(),
            'Toutes les références de billets doivent être uniques, même émises sur des départs différents.'
        );
    }
}
