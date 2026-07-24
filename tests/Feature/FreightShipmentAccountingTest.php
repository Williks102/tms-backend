<?php
// ══════════════════════════════════════════════════════════════════════════
// Cycle de vie complet d'une expédition fret : la livraison constate le
// revenu par une créance (debit 411 / credit 7072) — jamais un encaissement
// immédiat — et l'encaissement séparé solde cette créance (debit 571/credit
// 411). Vérifie aussi que l'écriture postée est bien équilibrée et que
// l'encaissement est refusé tant que la livraison n'a pas eu lieu.
// ══════════════════════════════════════════════════════════════════════════
namespace Tests\Feature;

use App\Models\Accounting\AccountingEntryLine;
use App\Models\FreightClient;
use App\Models\FreightShipment;
use App\Models\User;
use App\Models\Vehicle;
use Database\Seeders\AccountingChartSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FreightShipmentAccountingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AccountingChartSeeder::class);
    }

    public function test_livraison_puis_encaissement_postent_des_ecritures_equilibrees(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $client  = FreightClient::factory()->create();
        $truck   = Vehicle::factory()->create(['type' => 'truck']);

        $create = $this->actingAs($manager)->postJson('/api/v1/fret/shipments', [
            'freight_client_id' => $client->id,
            'origin_city'       => 'Abidjan',
            'destination_city'  => 'Bouaké',
            'distance_km'       => 350,
            'weight_tons'       => 5,
        ])->assertCreated();

        $shipmentId = $create->json('shipment.id');

        $this->actingAs($manager)->patchJson("/api/v1/fret/shipments/{$shipmentId}/assign", [
            'vehicle_id' => $truck->id,
        ])->assertOk();

        $this->actingAs($manager)->patchJson("/api/v1/fret/shipments/{$shipmentId}/status", [
            'status' => 'in_transit',
        ])->assertOk();

        // Encaissement impossible avant livraison
        $this->actingAs($manager)->postJson("/api/v1/fret/shipments/{$shipmentId}/pay", [
            'deposit_account' => '571',
        ])->assertStatus(422);

        $this->actingAs($manager)->patchJson("/api/v1/fret/shipments/{$shipmentId}/status", [
            'status' => 'delivered',
        ])->assertOk();

        $shipment = FreightShipment::findOrFail($shipmentId);
        $this->assertSame('invoiced', $shipment->payment_status);

        $invoiceLines = AccountingEntryLine::query()
            ->whereHas('entry', fn ($q) => $q->where('source_type', $shipment->getMorphClass())->where('source_id', $shipment->id))
            ->with('account')
            ->get();

        $this->assertCount(2, $invoiceLines);
        $this->assertEqualsWithDelta(
            $invoiceLines->sum('debit'),
            $invoiceLines->sum('credit'),
            0.01,
            'La créance de livraison doit être équilibrée (débit 411 = crédit 7072).'
        );
        $this->assertTrue($invoiceLines->contains(fn ($l) => $l->account->code === '411'));
        $this->assertTrue($invoiceLines->contains(fn ($l) => $l->account->code === '7072'));

        $this->actingAs($manager)->postJson("/api/v1/fret/shipments/{$shipmentId}/pay", [
            'deposit_account' => '571',
        ])->assertOk();

        $this->assertSame('paid', $shipment->fresh()->payment_status);

        // Deuxième tentative d'encaissement refusée (déjà payé)
        $this->actingAs($manager)->postJson("/api/v1/fret/shipments/{$shipmentId}/pay", [
            'deposit_account' => '571',
        ])->assertStatus(422);

        $allLines = AccountingEntryLine::query()
            ->whereHas('entry', fn ($q) => $q->where('source_type', $shipment->getMorphClass())->where('source_id', $shipment->id))
            ->get();

        // 2 écritures de 2 lignes chacune : facturation (411/7072) + encaissement (571/411)
        $this->assertCount(4, $allLines);
    }
}
