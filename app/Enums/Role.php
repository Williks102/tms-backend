<?php

namespace App\Enums;

enum Role: string
{
    case DG         = 'dg';
    case MANAGER    = 'manager';
    case DISPATCHER = 'dispatcher';
    case RH         = 'rh';
    case CAISSIER   = 'caissier';

    public function label(): string
    {
        return match ($this) {
            self::DG         => 'Directeur Général',
            self::MANAGER    => 'Manager',
            self::DISPATCHER => 'Dispatcher',
            self::RH         => 'RH',
            self::CAISSIER   => 'Caissier',
        };
    }
}
