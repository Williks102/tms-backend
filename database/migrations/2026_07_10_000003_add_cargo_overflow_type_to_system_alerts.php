<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const OLD_TYPES = [
        'fuel_anomaly', 'maintenance_due', 'doc_expiry',
        'overspeed', 'no_gate', 'driver_unavailable', 'overbooking',
    ];

    private const NEW_TYPES = [
        ...self::OLD_TYPES,
        'cargo_overflow',
    ];

    public function up(): void
    {
        // Voir 2024_01_05_000002_add_overbooking_type_to_system_alerts.php —
        // même garde, même raison (syntaxe Postgres uniquement, no-op sous
        // SQLite pour ne pas casser la suite de tests).
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
