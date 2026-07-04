<?php
// ══════════════════════════════════════════════════════════════════════════
// database/seeders/TicketSeeder.php
// ══════════════════════════════════════════════════════════════════════════
namespace Database\Seeders;

use App\Models\Departure;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Seeder;

class TicketSeeder extends Seeder
{
    private const PASSENGERS = [
        ['name' => 'Koffi N\'Guessan',    'phone' => '0707123456'],
        ['name' => 'Adjoua Konan',        'phone' => '0505987654'],
        ['name' => 'Sékou Traoré',        'phone' => '0102345678'],
        ['name' => 'Mariam Ouattara',     'phone' => '0707654321'],
        ['name' => 'Yao Kouassi',         'phone' => '0102987654'],
        ['name' => 'Fatou Bamba',         'phone' => '0505123789'],
        ['name' => 'Ibrahim Coulibaly',   'phone' => '0707456123'],
        ['name' => 'Akissi Brou',         'phone' => '0102765432'],
    ];

    public function run(): void
    {
        $cashier = User::where('email', 'caissier@tms-ci.com')->first();

        $departures = Departure::whereIn('status', ['scheduled', 'boarding', 'departed', 'arrived'])
            ->with('route')
            ->latest('departure_datetime')
            ->take(6)
            ->get();

        if ($departures->isEmpty()) {
            $this->command->warn('  Aucun départ trouvé pour les billets');
            return;
        }

        $count     = 0;
        $year      = now()->format('Y');
        $passenger = 0;

        foreach ($departures as $i => $departure) {
            $seatsToSell = min(3, max(0, $departure->seats_available - 2));

            for ($s = 0; $s < $seatsToSell; $s++) {
                $p       = self::PASSENGERS[$passenger % count(self::PASSENGERS)];
                $passenger++;

                $isOnline = $s % 2 === 0;
                $status   = match (true) {
                    $departure->status === 'arrived'  => 'boarded',
                    $departure->status === 'departed' => 'boarded',
                    default                            => 'paid',
                };

                $count++;
                $ref = sprintf('TCK-%s-%06d', $year, $count);

                Ticket::create([
                    'reference'       => $ref,
                    'departure_id'    => $departure->id,
                    'passenger_name'  => $p['name'],
                    'passenger_phone' => $p['phone'],
                    'seat_number'     => (string) ($s + 1),
                    'channel'         => $isOnline ? 'online' : 'physical',
                    'payment_method'  => $isOnline ? 'mobile_money' : 'cash',
                    'price_fcfa'      => $departure->route->base_fare,
                    'status'          => $status,
                    'sold_by'         => $isOnline ? null : $cashier?->id,
                    'purchased_at'    => $departure->departure_datetime->copy()->subHours(rand(1, 48)),
                    'boarded_at'      => $status === 'boarded' ? $departure->departure_datetime : null,
                ]);

                $departure->decrement('seats_available');
            }
        }

        // Un billet annulé pour illustrer le flux
        $cancelledDeparture = $departures->firstWhere('status', 'scheduled') ?? $departures->first();
        if ($cancelledDeparture && $cancelledDeparture->seats_available > 0) {
            $count++;
            $ref = sprintf('TCK-%s-%06d', $year, $count);

            Ticket::create([
                'reference'            => $ref,
                'departure_id'         => $cancelledDeparture->id,
                'passenger_name'       => 'Bakary Diomandé',
                'passenger_phone'      => '0707999888',
                'channel'              => 'physical',
                'payment_method'       => 'cash',
                'price_fcfa'           => $cancelledDeparture->route->base_fare,
                'status'               => 'cancelled',
                'sold_by'              => $cashier?->id,
                'purchased_at'         => now()->subHours(6),
                'cancellation_reason'  => 'Client indisponible — remboursement demandé',
            ]);
            // Pas de décrément: la place annulée reste disponible
        }

        $this->command->info("  Billets créés: {$count}");
    }
}
