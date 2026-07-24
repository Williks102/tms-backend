<?php
// ══════════════════════════════════════════════════════════════════════════
// Formule tonnage × km du module Fret — vérifie le calcul et le plancher
// minimum, et que la prévisualisation (/fret/quote) ne fait que refléter
// cette même formule serveur.
// ══════════════════════════════════════════════════════════════════════════
namespace Tests\Feature;

use App\Models\User;
use App\Services\Fret\FreightPricingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FreightPricingTest extends TestCase
{
    use RefreshDatabase;

    public function test_calcule_le_prix_proportionnel_au_tonnage_et_a_la_distance(): void
    {
        $pricing = app(FreightPricingService::class);

        $result = $pricing->calculate(weightTons: 5, distanceKm: 350);

        $this->assertEquals(5 * 350 * 150, $result['price_fcfa']);
    }

    public function test_applique_un_plancher_minimum(): void
    {
        $pricing = app(FreightPricingService::class);

        $result = $pricing->calculate(weightTons: 0.1, distanceKm: 1);

        $this->assertEquals(15000.0, $result['price_fcfa']);
    }

    public function test_endpoint_quote_reflete_la_formule_serveur(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);

        $response = $this->actingAs($manager)->postJson('/api/v1/fret/quote', [
            'weight_tons' => 5,
            'distance_km' => 350,
        ]);

        $response->assertOk();
        $response->assertJson(['price_fcfa' => 5 * 350 * 150]);
    }
}
