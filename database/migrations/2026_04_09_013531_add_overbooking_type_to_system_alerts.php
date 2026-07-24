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
        DB::statement('ALTER TABLE system_alerts DROP CONSTRAINT system_alerts_type_check');
        DB::statement(
            'ALTER TABLE system_alerts ADD CONSTRAINT system_alerts_type_check CHECK (type IN (' .
            collect(self::NEW_TYPES)->map(fn ($t) => "'{$t}'")->implode(',') .
            '))'
        );
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE system_alerts DROP CONSTRAINT system_alerts_type_check');
        DB::statement(
            'ALTER TABLE system_alerts ADD CONSTRAINT system_alerts_type_check CHECK (type IN (' .
            collect(self::OLD_TYPES)->map(fn ($t) => "'{$t}'")->implode(',') .
            '))'
        );
    }
};
