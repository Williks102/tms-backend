<?php
// ══════════════════════════════════════════════════════════════════════════
// database/seeders/IncidentSeeder.php
// ══════════════════════════════════════════════════════════════════════════
namespace Database\Seeders;

use App\Models\Departure;
use App\Models\Driver;
use App\Models\Incident;
use App\Models\IncidentAction;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Seeder;

class IncidentSeeder extends Seeder
{
    public function run(): void
    {
        $manager    = User::where('email', 'manager@tms-ci.com')->first();
        $dispatcher = User::where('email', 'dispatcher@tms-ci.com')->first();
        $departures = Departure::where('status', 'arrived')->with(['vehicle', 'driver'])->get();

        if ($departures->isEmpty()) {
            $this->command->warn('  Aucun départ trouvé pour les incidents');
            return;
        }

        $incidents = [
            [
                'category'    => 'mechanical',
                'severity'    => 'medium',
                'title'       => 'Panne climatisation en route',
                'description' => 'La climatisation du véhicule est tombée en panne à mi-parcours. Les passagers ont été incommodés par la chaleur. Arrêt de 20 minutes pour tentative de réparation.',
                'location'    => 'PK 185, Axe Abidjan-Bouaké',
                'status'      => 'resolved',
                'delay_days'  => 5,
                'resolution'  => 'Climatisation réparée au garage à l\'arrivée. Pièce défectueuse remplacée.',
                'actions'     => [
                    ['action_type' => 'repair', 'description' => 'Tentative réparation sur place — échec', 'cost' => 0],
                    ['action_type' => 'repair', 'description' => 'Réparation complète au garage Bouaké', 'cost' => 85000],
                ],
            ],
            [
                'category'    => 'road',
                'severity'    => 'low',
                'title'       => 'Retard suite embouteillage entrée Abidjan',
                'description' => 'Embouteillage important au péage de Sikensi sur l\'axe San-Pédro. Retard de 45 minutes sur l\'horaire prévu.',
                'location'    => 'Péage de Sikensi, RN1',
                'status'      => 'closed',
                'delay_days'  => 4,
                'resolution'  => 'Signalement aux autorités. Passagers informés du retard via le chauffeur.',
                'actions'     => [],
            ],
            [
                'category'    => 'accident',
                'severity'    => 'high',
                'title'       => 'Accrochage avec véhicule léger à Yamoussoukro',
                'description' => 'Collision légère avec un véhicule particulier au rond-point de Yamoussoukro. Dégâts matériels sur le pare-choc avant. Aucun blessé parmi les passagers. Constat amiable établi.',
                'location'    => 'Rond-point de la Paix, Yamoussoukro',
                'status'      => 'investigating',
                'delay_days'  => 2,
                'resolution'  => null,
                'actions'     => [
                    ['action_type' => 'police', 'description' => 'Déclaration au commissariat de Yamoussoukro — PV N°2025-YAM-0412', 'cost' => 0],
                    ['action_type' => 'repair', 'description' => 'Devis réparation pare-choc en attente', 'cost' => 0],
                ],
            ],
            [
                'category'    => 'mechanical',
                'severity'    => 'critical',
                'title'       => 'Crevaison double roue arrière gauche',
                'description' => 'Double crevaison soudaine sur l\'autoroute du Nord à hauteur de Singrobo. Véhicule immobilisé 2h30. Passagers evacuatés et pris en charge par un véhicule de remplacement dépêché depuis Abidjan.',
                'location'    => 'Autoroute du Nord PK 80, Singrobo',
                'status'      => 'resolved',
                'delay_days'  => 6,
                'resolution'  => 'Pneus remplacés. Véhicule convoyé au garage pour inspection complète du train roulant.',
                'actions'     => [
                    ['action_type' => 'tow', 'description' => 'Dépannage sur place — changement pneus', 'cost' => 45000],
                    ['action_type' => 'replacement_vehicle', 'description' => 'Bus CI-8830-KL dépêché pour récupérer les passagers', 'cost' => 0],
                    ['action_type' => 'repair', 'description' => 'Inspection complète train roulant au garage central', 'cost' => 120000],
                ],
            ],
            [
                'category'    => 'passenger',
                'severity'    => 'low',
                'title'       => 'Bagages en excès — conflit avec passager',
                'description' => 'Un passager refusait d\'enlever ses bagages excédentaires placés dans l\'allée centrale, créant un risque de sécurité. Situation réglée à l\'amiable après discussion.',
                'location'    => 'Départ Gare d\'Adjamé',
                'status'      => 'closed',
                'delay_days'  => 3,
                'resolution'  => 'Passager a accepté de déposer les bagages excédentaires en soute. Départ avec 15 min de retard.',
                'actions'     => [
                    ['action_type' => 'passenger_support', 'description' => 'Médiation entre chauffeur et passager — résolution amiable', 'cost' => 0],
                ],
            ],
            [
                'category'    => 'driver',
                'severity'    => 'medium',
                'title'       => 'Chauffeur signalé pour excès de vitesse répété',
                'description' => 'Passager a signalé au téléphone que le chauffeur roulait à une vitesse excessive (estimé 120 km/h en zone 80). Trois passagers ont exprimé leur mécontentement.',
                'location'    => 'Route Abidjan-Bouaké, entre Tiébissou et Bouaké',
                'status'      => 'open',
                'delay_days'  => 1,
                'resolution'  => null,
                'actions'     => [],
            ],
            [
                'category'    => 'mechanical',
                'severity'    => 'medium',
                'title'       => 'Surchauffe moteur — arrêt technique',
                'description' => 'Témoin de température moteur allumé à 60 km de Daloa. Arrêt préventif de 35 minutes. Appoint de liquide de refroidissement effectué. Voyage poursuivi après refroidissement.',
                'location'    => 'RN3, PK 350, avant Daloa',
                'status'      => 'resolved',
                'delay_days'  => 4,
                'resolution'  => 'Fuite de liquide de refroidissement identifiée. Réparation effectuée au garage de Daloa.',
                'actions'     => [
                    ['action_type' => 'repair', 'description' => 'Appoint liquide refroidissement sur place', 'cost' => 5000],
                    ['action_type' => 'repair', 'description' => 'Réparation fuite — remplacement durite', 'cost' => 35000],
                ],
            ],
            [
                'category'    => 'road',
                'severity'    => 'high',
                'title'       => 'Route barrée suite accident — déviation 3h',
                'description' => 'Accident de poids lourd bloquant totalement la RN1 dans les deux sens. Déviation obligatoire par piste latérale sur 25 km. Retard total de 3 heures.',
                'location'    => 'RN1, PK 220, avant Yamoussoukro',
                'status'      => 'closed',
                'delay_days'  => 5,
                'resolution'  => 'Déviation effectuée par piste de Toumodi. Passagers informés. Aucun dommage sur le véhicule malgré l\'état de la piste.',
                'actions'     => [
                    ['action_type' => 'other', 'description' => 'Information passagers et choix itinéraire alternatif', 'cost' => 0],
                ],
            ],
        ];

        $count = 0;
        foreach ($incidents as $i => $data) {
            $departure = $departures[$i % $departures->count()];
            $occurredAt = now()->subDays($data['delay_days'])->subHours(rand(1, 8));

            $incident = Incident::create([
                'reference'             => sprintf('INC-%s-%04d', now()->format('Y'), $i + 1),
                'departure_id'          => $departure->id,
                'vehicle_id'            => $departure->vehicle_id,
                'driver_id'             => $departure->driver_id,
                'reported_by'           => $dispatcher->id,
                'category'              => $data['category'],
                'severity'              => $data['severity'],
                'title'                 => $data['title'],
                'description'           => $data['description'],
                'location'              => $data['location'],
                'occurred_at'           => $occurredAt,
                'status'                => $data['status'],
                'resolution_notes'      => $data['resolution'] ?? null,
                'resolved_at'           => $data['resolution'] ? $occurredAt->addHours(rand(2, 48)) : null,
                'resolved_by'           => $data['resolution'] ? $manager->id : null,
                'financial_impact_fcfa' => null,
            ]);

            // Actions correctives
            $totalCost = 0;
            foreach ($data['actions'] as $action) {
                IncidentAction::create([
                    'incident_id' => $incident->id,
                    'action_type' => $action['action_type'],
                    'description' => $action['description'],
                    'taken_by'    => $manager->id,
                    'taken_at'    => $occurredAt->addMinutes(rand(30, 120)),
                    'cost_fcfa'   => $action['cost'],
                ]);
                $totalCost += $action['cost'];
            }

            // Met à jour le coût total si des actions ont un coût
            if ($totalCost > 0) {
                $incident->update(['financial_impact_fcfa' => $totalCost]);
            }

            $count++;
        }

        $this->command->info("  Incidents créés: {$count}");
    }
}
