<?php
// ══════════════════════════════════════════════════════════════════════════
// Le tarif fret (FCFA/tonne-km, plancher minimum) est éditable en base
// (freight_pricing_settings, singleton) plutôt que figé dans le code —
// vérifie que la mise à jour est bien reflétée par le calcul, et que
// seul un manager peut la modifier (agent_fret en lecture seule).
// ══════════════════════════════════════════════════════════════════════════
namespace Tests\Feature;

use App\Models\FreightPricingSetting;
use App\Models\User;
use App\Services\Fret\FreightPricingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FreightPricingSettingTest extends TestCase
{
    use RefreshDatabase;

    public function test_valeurs_par_defaut_creees_au_premier_acces(): void
    {
        $settings = FreightPricingSetting::current();

        $this->assertEquals(150.0, (float) $settings->rate_per_ton_km_fcfa);
        $this->assertEquals(15000.0, (float) $settings->minimum_fee_fcfa);
    }

    public function test_manager_peut_modifier_le_tarif_et_le_calcul_le_reflete(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);

        $this->actingAs($manager)->putJson('/api/v1/fret/pricing-settings', [
            'rate_per_ton_km_fcfa' => 200,
            'minimum_fee_fcfa'     => 20000,
        ])->assertOk();

        $pricing = app(FreightPricingService::class);
        $result  = $pricing->calculate(weightTons: 5, distanceKm: 350);

        $this->assertEquals(5 * 350 * 200, $result['price_fcfa']);
    }

    public function test_agent_fret_ne_peut_pas_modifier_le_tarif(): void
    {
        $agent = User::factory()->create(['role' => 'agent_fret']);

        $this->actingAs($agent)->putJson('/api/v1/fret/pricing-settings', [
            'rate_per_ton_km_fcfa' => 200,
            'minimum_fee_fcfa'     => 20000,
        ])->assertStatus(403);
    }

    public function test_agent_fret_peut_lire_le_tarif(): void
    {
        $agent = User::factory()->create(['role' => 'agent_fret']);

        $this->actingAs($agent)->getJson('/api/v1/fret/pricing-settings')
            ->assertOk()
            ->assertJsonStructure(['settings' => ['rate_per_ton_km_fcfa', 'minimum_fee_fcfa']]);
    }
}
