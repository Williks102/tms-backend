<?php
// ══════════════════════════════════════════════════════════════════════════
// 2024_01_03_000002_create_fuel_consumption_logs_table.php
// ══════════════════════════════════════════════════════════════════════════
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fuel_consumption_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->foreignId('departure_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fuel_voucher_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('liters_consumed', 6, 2);
            $table->decimal('distance_km', 8, 2);
            $table->decimal('consumption_per_100km', 5, 2)
                  ->comment('Calculé: (liters_consumed / distance_km) × 100');
            $table->decimal('mileage_before', 10, 2)->comment('Km avant départ');
            $table->decimal('mileage_after', 10, 2)->comment('Km après retour');
            $table->dateTime('recorded_at');
            $table->foreignId('recorded_by')->constrained('users');
            $table->timestamps();

            $table->unique('departure_id'); // Un seul log par départ
            $table->index(['vehicle_id', 'recorded_at']);
        });
    }

    public function down(): void { Schema::dropIfExists('fuel_consumption_logs'); }
};


// ══════════════════════════════════════════════════════════════════════════
// 2024_01_03_000003_create_maintenance_plans_table.php
// ══════════════════════════════════════════════════════════════════════════
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->enum('type', [
                'oil_change',    // Vidange
                'tire',          // Pneus
                'brake',         // Freins
                'full_service',  // Révision complète
                'other',
            ]);
            $table->decimal('trigger_km', 10, 2)->nullable()
                  ->comment('Kilométrage déclencheur (ex: 210000)');
            $table->date('trigger_date')->nullable()
                  ->comment('Ou à date fixe');
            $table->decimal('interval_km', 8, 2)->nullable()
                  ->comment('Intervalle récurrent (ex: 10000 km)');
            $table->unsignedInteger('interval_days')->nullable()
                  ->comment('Intervalle en jours');
            $table->decimal('alert_km_before', 6, 2)->default(500)
                  ->comment('Déclenchement alerte X km avant le seuil');
            $table->enum('status', ['upcoming', 'due', 'overdue', 'completed'])
                  ->default('upcoming');
            $table->decimal('estimated_cost', 10, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['vehicle_id', 'status']);
            $table->index('trigger_km');
        });
    }

    public function down(): void { Schema::dropIfExists('maintenance_plans'); }
};


// ══════════════════════════════════════════════════════════════════════════
// 2024_01_03_000004_create_maintenance_records_table.php
// ══════════════════════════════════════════════════════════════════════════
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->foreignId('maintenance_plan_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('type', ['oil_change', 'tire', 'brake', 'full_service', 'other']);
            $table->dateTime('performed_at');
            $table->decimal('mileage_at_service', 10, 2);
            $table->string('garage_name', 100);
            $table->decimal('cost_fcfa', 12, 2);
            $table->json('parts_replaced')->nullable()
                  ->comment('["filtre huile", "courroie distribution"]');
            $table->decimal('next_service_km', 10, 2)->nullable()
                  ->comment('Prochain déclencheur calculé automatiquement');
            $table->foreignId('performed_by')->constrained('users');
            $table->string('invoice_path', 255)->nullable()
                  ->comment('Chemin vers la facture scannée');
            $table->timestamps();

            $table->index(['vehicle_id', 'performed_at']);
        });
    }

    public function down(): void { Schema::dropIfExists('maintenance_records'); }
};


// ══════════════════════════════════════════════════════════════════════════
// 2024_01_03_000005_create_system_alerts_table.php
// Table partagée par tous les modules
// ══════════════════════════════════════════════════════════════════════════
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_alerts', function (Blueprint $table) {
            $table->id();
            $table->enum('type', [
                'fuel_anomaly',      // Bon carburant anormal
                'maintenance_due',   // Maintenance imminente ou dépassée
                'doc_expiry',        // Document chauffeur expirant
                'overspeed',         // Excès de vitesse signalé
                'no_gate',           // Aucun quai disponible
                'driver_unavailable',// Chauffeur non conforme (repos)
            ]);
            $table->enum('severity', ['info', 'warning', 'critical']);
            // Polymorphique: peut pointer vers Vehicle, Driver, Departure...
            $table->string('entity_type', 100)->comment('App\\Models\\Vehicle');
            $table->unsignedBigInteger('entity_id');
            $table->text('message')->comment('Message lisible par le gestionnaire');
            $table->json('metadata')->nullable()
                  ->comment('Données contextuelles: voucher_id, ratio, km_remaining...');
            $table->boolean('is_resolved')->default(false);
            $table->dateTime('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Index pour le dashboard: alertes actives triées par sévérité
            $table->index(['is_resolved', 'severity', 'created_at']);
            $table->index(['entity_type', 'entity_id']);
        });
    }

    public function down(): void { Schema::dropIfExists('system_alerts'); }
};
