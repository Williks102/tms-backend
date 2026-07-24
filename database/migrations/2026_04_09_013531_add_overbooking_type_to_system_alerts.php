<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const OLD_TYPES = [
        'fuel_anomaly', 'maintenance_due', 'doc_expiry',
        'overspeed', 'no_gate', 'driver_unavailable',
    ];

    private const NEW_TYPES = [
        ...self::OLD_TYPES,
        'overbooking',
    ];

    public function up(): void
    {
        // DROP/ADD CONSTRAINT nommée : syntaxe Postgres uniquement. SQLite (utilisé
        // par la suite de tests, phpunit.xml) matérialise l'enum comme un CHECK
        // inline non nommé, non modifiable par cette syntaxe — no-op hors pgsql,
        // sans risque : Eloquent ne valide jamais 'type' contre ce CHECK.
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE system_alerts DROP CONSTRAINT system_alerts_type_check');
        DB::statement(
            'ALTER TABLE system_alerts ADD CONSTRAINT system_alerts_type_check CHECK (type IN (' .
            collect(self::NEW_TYPES)->map(fn ($t) => "'{$t}'")->implode(',') .
            '))'
        );
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE system_alerts DROP CONSTRAINT system_alerts_type_check');
        DB::statement(
            'ALTER TABLE system_alerts ADD CONSTRAINT system_alerts_type_check CHECK (type IN (' .
            collect(self::OLD_TYPES)->map(fn ($t) => "'{$t}'")->implode(',') .
            '))'
        );
    }
};
