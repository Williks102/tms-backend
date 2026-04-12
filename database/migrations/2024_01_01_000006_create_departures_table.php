<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departures', function (Blueprint $table) {
            $table->id();

            // ── Relations obligatoires ──────────────────────────────────
            $table->foreignId('route_id')
                  ->constrained()
                  ->restrictOnDelete()
                  ->comment('La ligne empruntée');

            // ── Relations optionnelles (affectées après création) ───────
            $table->foreignId('vehicle_id')
                  ->nullable()
                  ->constrained()
                  ->nullOnDelete()
                  ->comment('Null = pas encore affecté');

            // drivers est défini dans le module Chauffeurs,
            // on utilise unsignedBigInteger pour éviter la dépendance circulaire
            $table->unsignedBigInteger('driver_id')
                  ->nullable()
                  ->comment('Affecté après vérification temps de repos');

            $table->foreignId('schedule_template_id')
                  ->nullable()
                  ->constrained()
                  ->nullOnDelete()
                  ->comment('Null = départ manuel créé par le gestionnaire');

            // ── Horaires ────────────────────────────────────────────────
            $table->dateTime('departure_datetime')
                  ->comment('Date et heure planifiées du départ');
            $table->dateTime('estimated_arrival')
                  ->comment('Calculé: departure_datetime + route.estimated_duration_min');
            $table->dateTime('actual_departure')->nullable()
                  ->comment('Rempli au moment du départ réel');
            $table->dateTime('actual_arrival')->nullable()
                  ->comment('Rempli à l\'arrivée');

            // ── Quai ─────────────────────────────────────────────────
            $table->string('boarding_gate', 10)->nullable()
                  ->comment('Attribué automatiquement par GateAssignmentService');

            // ── Places ──────────────────────────────────────────────────
            $table->unsignedSmallInteger('seats_available')
                  ->default(0)
                  ->comment('Mis à jour depuis vehicle.capacity lors de l\'affectation');

            // ── Statut ──────────────────────────────────────────────────
            $table->enum('status', [
                'scheduled',    // Programmé, pas encore ouvert à l'embarquement
                'boarding',     // Ouvert à l'embarquement au quai
                'departed',     // Bus parti
                'arrived',      // Arrivé à destination
                'cancelled',    // Annulé
            ])->default('scheduled');

            $table->text('cancellation_reason')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // ── Index pour les requêtes fréquentes ──────────────────────
            $table->index('status');
            $table->index('departure_datetime');
            $table->index(['route_id', 'departure_datetime']);
            $table->index(['vehicle_id', 'departure_datetime']);
            $table->index(['driver_id', 'departure_datetime']);
            $table->index(['status', 'departure_datetime']); // Pour le "live"

            // Contrainte: un véhicule ne peut pas être sur deux départs en même temps
            // (géré au niveau Service, pas en BDD car les plages se chevauchent)
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('departures');
    }
};
