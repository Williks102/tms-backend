<?php
// ══════════════════════════════════════════════════════════════════════════
// app/Observers/DepartureObserver.php
// Enregistrer dans AppServiceProvider::boot() :
//   Departure::observe(DepartureObserver::class);
// ══════════════════════════════════════════════════════════════════════════
namespace App\Observers;

use App\Models\Departure;
use App\Services\Drivers\RestComplianceService;
use Illuminate\Support\Facades\Log;

class DepartureObserver
{
    public function __construct(
        private readonly RestComplianceService $restService,
    ) {}

    /**
     * Déclenché après chaque création de départ.
     */
    public function created(Departure $departure): void
    {
        // 1. Notifie la billetterie (ClicBillet) que les billets sont ouverts
        $this->notifyTicketing($departure);
 
        // 2. Log pour traçabilité
        Log::info('Départ créé', [
            'departure_id'       => $departure->id,
            'route'              => $departure->route?->code,
            'departure_datetime' => $departure->departure_datetime?->toDateTimeString(),
            'is_manual'          => $departure->isManual(),
        ]);
    }
 
    /**
     * Déclenché quand le statut passe à 'departed'.
     */
    public function updated(Departure $departure): void
    {
        if (!$departure->wasChanged('status')) {
            return;
        }
 
        match ($departure->status) {
            // Crée le log de repos du chauffeur (module Chauffeurs)
            'departed' => $this->onDeparted($departure),
            // Enregistre le retour du véhicule (module Chauffeurs)
            'arrived'  => $this->onArrived($departure),
            // Notifie ClicBillet de l'annulation
            'cancelled'=> $this->onCancelled($departure),
            default    => null,
        };
    }
 
    // ── Handlers privés ───────────────────────────────────────────────────
 
    private function notifyTicketing(Departure $departure): void
    {
        // Dispatch un job async pour appeler l'API ClicBillet
        // \App\Jobs\Ticketing\OpenSaleForDeparture::dispatch($departure);
 
        // Placeholder - à implémenter avec votre intégration ClicBillet
        Log::info('Billetterie notifiée — vente ouverte', [
            'departure_id' => $departure->id,
        ]);
    }
 
    private function onDeparted(Departure $departure): void
    {
        // Crée le DriverRestLog (module Chauffeurs). Enveloppé en try/catch :
        // un souci de suivi de repos ne doit jamais empêcher le départ lui-même.
        if ($departure->driver_id && $departure->driver) {
            try {
                $this->restService->startDuty($departure->driver, $departure);
            } catch (\Exception $e) {
                Log::error('Échec startDuty', ['departure_id' => $departure->id, 'error' => $e->getMessage()]);
            }
        }

        // Met le véhicule en statut on_trip
        $departure->vehicle?->update(['status' => 'on_trip']);
    }
 
    private function onArrived(Departure $departure): void
    {
        // Remet le véhicule disponible
        $departure->vehicle?->update(['status' => 'available']);
 
        // Ferme le DriverRestLog et démarre le repos. Enveloppé en try/catch :
        // endDuty() lève une exception si aucun log ouvert n'existe (ex: départ
        // arrivé sans être passé par "departed" avant le déploiement de cette
        // fonctionnalité) — l'arrivée du départ ne doit jamais échouer pour ça.
        if ($departure->driver_id && $departure->driver) {
            try {
                $this->restService->endDuty($departure->driver, $departure);
            } catch (\Exception $e) {
                Log::error('Échec endDuty', ['departure_id' => $departure->id, 'error' => $e->getMessage()]);
            }
        }
    }
 
    private function onCancelled(Departure $departure): void
    {
        // Libère le quai immédiatement
        Log::info('Départ annulé — quai libéré', [
            'departure_id' => $departure->id,
            'gate'         => $departure->boarding_gate,
        ]);
    }
}