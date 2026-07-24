<?php
// ══════════════════════════════════════════════════════════════════════════
// Même invariant que TicketConcurrencyTest : la référence FRT-AAAA-NNNNNN est
// dérivée de l'id auto-incrémenté (FreightShipmentService::create()), jamais
// d'un count()+1 — garantit l'unicité quel que soit l'ordre de création,
// même sur des clients différents.
// ══════════════════════════════════════════════════════════════════════════
namespace Tests\Feature;

use App\Models\FreightClient;
use App\Models\FreightShipment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FreightShipmentReferenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_references_uniques_sur_plusieurs_clients(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $clients = FreightClient::factory()->count(2)->create();

        foreach ($clients as $client) {
            foreach (range(1, 3) as $i) {
                $response = $this->actingAs($manager)->postJson('/api/v1/fret/shipments', [
                    'freight_client_id' => $client->id,
                    'origin_city'       => 'Abidjan',
                    'destination_city'  => 'Bouaké',
                    'distance_km'       => 350,
                    'weight_tons'       => 5,
                ]);

                $response->assertCreated();
            }
        }

        $this->assertSame(6, FreightShipment::count());
        $this->assertSame(
            FreightShipment::count(),
            FreightShipment::pluck('reference')->unique()->count(),
            'Toutes les références FRT doivent être uniques, même émises pour des clients différents.'
        );
    }
}
