<?php

// ══════════════════════════════════════════════════════════════════════════
// app/Services/Fuel/AlertDispatcher.php
// Service central de création d'alertes — utilisé par tous les modules
// ══════════════════════════════════════════════════════════════════════════
namespace App\Services\Fuel;

use App\Models\SystemAlert;
use Illuminate\Database\Eloquent\Model;

class AlertDispatcher
{
    /**
     * Crée une alerte système.
     * Évite les doublons: une alerte identique non résolue ne sera pas recréée.
     */
    public function create(
        string $type,
        string $severity,
        Model  $entity,
        string $message,
        array  $metadata = []
    ): SystemAlert {
        // Déduplique: si une alerte active du même type sur la même entité existe, on la met à jour
        return SystemAlert::updateOrCreate(
            [
                'type'        => $type,
                'entity_type' => get_class($entity),
                'entity_id'   => $entity->getKey(),
                'is_resolved' => false,
            ],
            [
                'severity' => $severity,
                'message'  => $message,
                'metadata' => $metadata,
            ]
        );
    }

    /**
     * Résout automatiquement toutes les alertes actives d'un type
     * sur une entité (ex: maintenance effectuée → résoudre l'alerte).
     */
    public function resolveFor(Model $entity, string $type): void
    {
        SystemAlert::where('entity_type', get_class($entity))
            ->where('entity_id', $entity->getKey())
            ->where('type', $type)
            ->where('is_resolved', false)
            ->update([
                'is_resolved' => true,
                'resolved_at' => now(),
            ]);
    }
}