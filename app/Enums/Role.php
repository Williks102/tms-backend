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

    public function label(): string
    {
        return match ($this) {
            self::DG         => 'Directeur Général',
            self::MANAGER    => 'Manager',
            self::DISPATCHER => 'Dispatcher',
            self::RH         => 'RH',
            self::CAISSIER   => 'Caissier',
            self::DRIVER     => 'Chauffeur',
            self::CONTROLEUR => 'Contrôleur',
            self::COMPTABLE  => 'Comptable',
        };
    }
}
