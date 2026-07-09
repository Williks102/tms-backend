<?php
// ══════════════════════════════════════════════════════════════════════════
// app/Jobs/Shared/CheckDocumentExpiry.php
// Chauffeurs ET véhicules dans le même job — évite de dupliquer la logique
// d'alerte/notification pour deux domaines qui suivent exactement le même
// principe (colonnes d'expiration dédiées, pas la table de documents brute).
// ══════════════════════════════════════════════════════════════════════════
namespace App\Jobs\Shared;

use App\Enums\Role;
use App\Models\Driver;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\Fuel\AlertDispatcher;
use App\Services\Notifications\NotificationService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CheckDocumentExpiry implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const WARNING_DAYS = 30;

    public function handle(AlertDispatcher $alerts, NotificationService $notifications): void
    {
        $threshold    = now()->addDays(self::WARNING_DAYS);
        $recipientIds = User::whereIn('role', [Role::MANAGER->value, Role::RH->value])->pluck('id')->all();

        foreach (Driver::whereNotNull('license_expires_at')->where('license_expires_at', '<=', $threshold)->get() as $driver) {
            $this->flag($alerts, $notifications, $recipientIds, $driver, 'Permis de conduire', $driver->license_expires_at, "{$driver->first_name} {$driver->last_name}");
        }
        foreach (Driver::whereNotNull('medical_expires_at')->where('medical_expires_at', '<=', $threshold)->get() as $driver) {
            $this->flag($alerts, $notifications, $recipientIds, $driver, 'Visite médicale', $driver->medical_expires_at, "{$driver->first_name} {$driver->last_name}");
        }
        foreach (Vehicle::whereNotNull('insurance_expires_at')->where('insurance_expires_at', '<=', $threshold)->get() as $vehicle) {
            $this->flag($alerts, $notifications, $recipientIds, $vehicle, 'Assurance', $vehicle->insurance_expires_at, $vehicle->plate_number);
        }
        foreach (Vehicle::whereNotNull('controle_technique_expires_at')->where('controle_technique_expires_at', '<=', $threshold)->get() as $vehicle) {
            $this->flag($alerts, $notifications, $recipientIds, $vehicle, 'Contrôle technique', $vehicle->controle_technique_expires_at, $vehicle->plate_number);
        }
    }

    private function flag(
        AlertDispatcher $alerts,
        NotificationService $notifications,
        array $recipientIds,
        Model $entity,
        string $documentLabel,
        Carbon $expiresAt,
        string $entityLabel,
    ): void {
        $severity = $expiresAt->isPast() ? 'critical' : 'warning';
        $verb     = $expiresAt->isPast() ? 'a expiré' : 'expire';
        $message  = "{$documentLabel} de {$entityLabel} {$verb} le {$expiresAt->format('d/m/Y')}";

        $alerts->create(
            type:     'doc_expiry',
            severity: $severity,
            entity:   $entity,
            message:  $message,
            metadata: ['document' => $documentLabel, 'expires_at' => $expiresAt->toDateString()],
        );

        if ($recipientIds) {
            $notifications->notify($recipientIds, 'doc_expiry', 'Document arrivant à expiration', $message, [
                'entity_type' => $entity->getMorphClass(),
                'entity_id'   => $entity->getKey(),
            ]);
        }
    }
}
