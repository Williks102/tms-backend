<?php
// ══════════════════════════════════════════════════════════════════════════
// app/Services/Incidents/IncidentService.php
// ══════════════════════════════════════════════════════════════════════════
namespace App\Services\Incidents;

use App\Models\Incident;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class IncidentService
{
    public function __construct(
        private readonly IncidentAlertService $alertService,
    ) {}

    /**
     * Crée un incident avec référence automatique.
     */
    public function create(array $data, int $reportedBy): Incident
    {
        return DB::transaction(function () use ($data, $reportedBy) {

            // Génération référence unique: INC-AAAA-NNNN
            $year  = now()->format('Y');
            $count = Incident::whereYear('created_at', $year)->count() + 1;
            $ref   = sprintf('INC-%s-%04d', $year, $count);

            $incident = Incident::create([
                'reference'             => $ref,
                'departure_id'          => $data['departure_id'] ?? null,
                'vehicle_id'            => $data['vehicle_id'],
                'driver_id'             => $data['driver_id'],
                'reported_by'           => $reportedBy,
                'category'              => $data['category'],
                'severity'              => $data['severity'],
                'title'                 => $data['title'],
                'description'           => $data['description'],
                'location'              => $data['location'] ?? null,
                'occurred_at'           => $data['occurred_at'],
                'status'                => 'open',
                'financial_impact_fcfa' => $data['financial_impact_fcfa'] ?? null,
            ]);

            // Incident critique → alerte immédiate + véhicule en maintenance
            if ($incident->isCritical()) {
                $this->alertService->fireAlert($incident);
            }

            // Ajout d'une note sur le départ associé si encore actif
            if ($incident->departure_id) {
                $dep = $incident->departure;
                if ($dep && in_array($dep->status, ['boarding', 'departed'])) {
                    $dep->update([
                        'notes' => trim(($dep->notes ?? '') . "\n[{$ref}] {$incident->title}")
                    ]);
                }
            }

            Log::info('Incident créé', [
                'reference' => $ref,
                'severity'  => $incident->severity,
                'category'  => $incident->category,
            ]);

            return $incident->fresh(['vehicle', 'driver', 'departure.route']);
        });
    }

    /**
     * Transitions de statut autorisées.
     */
    private const VALID_TRANSITIONS = [
        'open'          => ['investigating', 'resolved'],
        'investigating' => ['resolved'],
        'resolved'      => ['closed'],
        'closed'        => [],
    ];

    public function updateStatus(Incident $incident, string $newStatus, array $data = []): Incident
    {
        $allowed = self::VALID_TRANSITIONS[$incident->status] ?? [];

        if (!in_array($newStatus, $allowed)) {
            throw new \Exception(
                "Transition invalide: {$incident->status} → {$newStatus}. "
                . "Autorisé: " . implode(', ', $allowed)
            );
        }

        $updates = ['status' => $newStatus];

        if (in_array($newStatus, ['resolved', 'closed'])) {
            $updates['resolved_at']       = now();
            $updates['resolution_notes']  = $data['resolution_notes']
                ?? throw new \Exception('Les notes de résolution sont obligatoires');
        }

        $incident->update($updates);

        // Si résolu et catégorie mécanique → véhicule peut redevenir disponible
        if ($newStatus === 'resolved' && in_array($incident->category, ['mechanical', 'accident'])) {
            // Le gestionnaire doit confirmer manuellement via PATCH /vehicles/{id}/status
            // On ajoute juste une note
            Log::info("Incident {$incident->reference} résolu — vérifier statut véhicule {$incident->vehicle->plate_number}");
        }

        return $incident->fresh();
    }
}