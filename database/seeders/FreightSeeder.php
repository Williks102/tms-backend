<?php
// ══════════════════════════════════════════════════════════════════════════
// database/seeders/FreightSeeder.php
// ══════════════════════════════════════════════════════════════════════════
namespace Database\Seeders;

use App\Models\FreightClient;
use App\Models\FreightShipment;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\Fret\FreightPricingService;
use Illuminate\Database\Seeder;

class FreightSeeder extends Seeder
{
    public function run(): void
    {
        $creator = User::where('role', 'agent_fret')->first()
            ?? User::where('role', 'manager')->firstOrFail();

        $pricing = app(FreightPricingService::class);

        $clients = [
            ['company_name' => 'CI Négoce SARL',      'contact_name' => 'Adama Traoré',  'phone' => '0102030405', 'email' => 'contact@cinegoce.ci'],
            ['company_name' => 'Ivoire Cacao Export',  'contact_name' => 'Marie Kouassi',  'phone' => '0708091011', 'email' => 'logistique@ivoirecacao.ci'],
            ['company_name' => 'Distribution Nord SA', 'contact_name' => 'Ismaël Coulibaly', 'phone' => '0506070809', 'email' => null],
        ];

        $clientModels = collect($clients)->map(fn ($c) => FreightClient::updateOrCreate(
            ['company_name' => $c['company_name']],
            $c,
        ));

        $truck = Vehicle::where('type', 'truck')->first();

        $shipments = [
            ['origin_city' => 'Abidjan', 'destination_city' => 'Bouaké',   'distance_km' => 350, 'weight_tons' => 8,  'status' => 'delivered', 'payment_status' => 'paid'],
            ['origin_city' => 'Abidjan', 'destination_city' => 'San-Pédro', 'distance_km' => 330, 'weight_tons' => 12, 'status' => 'delivered', 'payment_status' => 'invoiced'],
            ['origin_city' => 'Abidjan', 'destination_city' => 'Korhogo',  'distance_km' => 630, 'weight_tons' => 6,  'status' => 'in_transit', 'payment_status' => 'pending'],
            ['origin_city' => 'Yamoussoukro', 'destination_city' => 'Abidjan', 'distance_km' => 240, 'weight_tons' => 4, 'status' => 'pending', 'payment_status' => 'pending'],
        ];

        foreach ($shipments as $index => $data) {
            $client = $clientModels[$index % $clientModels->count()];
            $price  = $pricing->calculate((float) $data['weight_tons'], (float) $data['distance_km'])['price_fcfa'];

            $existing = FreightShipment::where('freight_client_id', $client->id)
                ->where('origin_city', $data['origin_city'])
                ->where('destination_city', $data['destination_city'])
                ->first();

            if ($existing) {
                continue;
            }

            $shipment = FreightShipment::create([
                'reference'         => 'PENDING-SEED-' . $index,
                'freight_client_id' => $client->id,
                'vehicle_id'        => in_array($data['status'], ['assigned', 'in_transit', 'delivered'], true) ? $truck?->id : null,
                'origin_city'       => $data['origin_city'],
                'destination_city'  => $data['destination_city'],
                'distance_km'       => $data['distance_km'],
                'weight_tons'       => $data['weight_tons'],
                'price_fcfa'        => $price,
                'status'            => $data['status'],
                'payment_status'    => $data['payment_status'],
                'created_by'        => $creator->id,
            ]);

            $shipment->update(['reference' => sprintf('FRT-%s-%06d', now()->format('Y'), $shipment->id)]);
        }

        $this->command->info('  Fret: ' . $clientModels->count() . ' clients, ' . count($shipments) . ' expéditions');
    }
}
