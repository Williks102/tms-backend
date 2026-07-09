<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parcels', function (Blueprint $table) {
            $table->id();

            $table->string('tracking_number')->unique();
            // Secret partagé communiqué par l'expéditeur au destinataire —
            // jamais imprimé sur l'étiquette collée au colis, uniquement sur
            // le reçu expéditeur. Remplace la vérification d'identité.
            $table->string('pickup_code', 6);

            $table->foreignId('departure_id')->constrained()->restrictOnDelete();
            $table->foreignId('destination_stop_id')->nullable()->constrained('route_stops')->nullOnDelete();

            $table->string('sender_name', 150);
            $table->string('sender_phone', 30);
            $table->string('recipient_name', 150);
            $table->string('recipient_phone', 30);

            $table->enum('category', ['standard', 'fragile', 'perissable', 'liquide'])->default('standard');
            $table->text('description')->nullable();
            $table->decimal('weight_kg', 8, 2);
            $table->decimal('declared_value_fcfa', 12, 2);

            $table->decimal('transport_fee_fcfa', 10, 2);
            $table->decimal('insurance_fee_fcfa', 10, 2);
            $table->decimal('total_fee_fcfa', 10, 2);

            $table->enum('payment_responsibility', ['expediteur', 'destinataire'])->default('expediteur');
            $table->enum('payment_status', ['pending', 'paid'])->default('pending');

            // Pas d'état "en_transit" séparé — déductible de departure.status
            // (scheduled/boarding/departed) croisé avec status=enregistre,
            // évite un état dupliqué qui pourrait se désynchroniser.
            $table->enum('status', ['enregistre', 'arrive', 'retire', 'retourne', 'perdu', 'annule'])
                  ->default('enregistre');

            $table->foreignId('registered_by')->constrained('users');
            $table->foreignId('released_by')->nullable()->constrained('users')->nullOnDelete();

            $table->dateTime('registered_at');
            $table->dateTime('arrived_at')->nullable();
            $table->dateTime('released_at')->nullable();
            $table->dateTime('returned_at')->nullable();
            $table->text('exception_reason')->nullable();

            $table->foreignId('accounting_entry_id')->nullable()->constrained('accounting_entries')->nullOnDelete();

            $table->timestamps();

            $table->index('status');
            $table->index(['departure_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parcels');
    }
};
