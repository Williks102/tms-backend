<?php

namespace App\Enums;

enum Role: string
{
    case DG         = 'dg';
    case MANAGER    = 'manager';
    case DISPATCHER = 'dispatcher';
    case RH         = 'rh';
    case CAISSIER   = 'caissier';
    case DRIVER     = 'driver';
    case CONTROLEUR = 'controleur';
    case COMPTABLE  = 'comptable';
    case AGENT_COLIS = 'agent_colis';

    // Réservé au porteur du projet — jamais assignable via la création de
    // personnel classique (EmployeeController), jamais seedé automatiquement
    // pour un nouveau client (voir tms:create-super-admin et
    // runbook-nouveau-client.md). Gère le statut d'abonnement SaaS et le
    // rapport de facturation de CE déploiement — voir Services/SuperAdmin.
    case SUPER_ADMIN = 'super_admin';

    public function label(): string
    {
        return match ($this) {
            self::DG          => 'Directeur Général',
            self::MANAGER     => 'Manager',
            self::DISPATCHER  => 'Dispatcher',
            self::RH          => 'RH',
            self::CAISSIER    => 'Caissier',
            self::DRIVER      => 'Chauffeur',
            self::CONTROLEUR  => 'Contrôleur',
            self::COMPTABLE   => 'Comptable',
            self::AGENT_COLIS => 'Agent colis',
            self::SUPER_ADMIN => 'Super Admin',
        };
    }
}
