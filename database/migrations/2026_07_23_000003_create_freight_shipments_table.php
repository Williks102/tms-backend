<?php
// ══════════════════════════════════════════════════════════════════════════
// database/migrations/2026_07_23_000003_create_freight_shipments_table.php
// Expédition fret — camions/trajets dédiés, indépendants du planning
// passagers (departures/routes). Facturation à crédit : payment_status suit
// le cycle pending (rien dû) → invoiced (livré, créance 411 ouverte) → paid.
// ══════════════════════════════════════════════════════════════════════════
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('freight_shipments', function (Blueprint $table) {
            $table->id();

            $table->string('reference')->unique()->comment('FRT-AAAA-NNNNNN');

            $table->foreignId('freight_client_id')
                  ->constrained()
                  ->restrictOnDelete();

            $table->foreignId('vehicle_id')
                  ->nullable()
                  ->constrained()
                  ->nullOnDelete()
                  ->comment('Camion (vehicles.type = truck) — null tant que non affecté');

            // Comme departures.driver_id : unsignedBigInteger brut pour éviter
            // la dépendance circulaire avec le module Chauffeurs.
            $table->unsignedBigInteger('driver_id')->nullable();

            $table->string('origin_city');
            $table->string('destination_city');
            $table->decimal('distance_km', 8, 2);
            $table->decimal('weight_tons', 8, 2);
            $table->text('cargo_description')->nullable();
            $table->decimal('price_fcfa', 12, 2);

            $table->enum('status', ['pending', 'assigned', 'in_transit', 'delivered', 'cancelled'])
                  ->default('pending');

            $table->enum('payment_status', ['pending', 'invoiced', 'paid'])
                  ->default('pending')
                  ->comment('invoiced = livré, créance 411 ouverte ; paid = encaissé (571/521)');

            $table->dateTime('scheduled_at')->nullable();
            $table->dateTime('departed_at')->nullable();
            $table->dateTime('delivered_at')->nullable();
            $table->text('cancellation_reason')->nullable();

            $table->foreignId('created_by')
                  ->constrained('users')
                  ->restrictOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('freight_client_id');
            $table->index(['status', 'scheduled_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('freight_shipments');
    }
};
