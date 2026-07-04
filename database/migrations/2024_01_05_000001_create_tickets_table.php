<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();

            $table->string('reference')->unique()->comment('TCK-AAAA-NNNNNN');

            $table->foreignId('departure_id')
                  ->constrained()
                  ->restrictOnDelete();

            // ── Passager ─────────────────────────────────────────────────
            $table->string('passenger_name');
            $table->string('passenger_phone', 30)->nullable();
            $table->string('seat_number', 10)->nullable();

            // ── Vente ────────────────────────────────────────────────────
            $table->enum('channel', ['physical', 'online'])
                  ->comment('physical = guichet (caissier), online = billetterie web/mobile');
            $table->enum('payment_method', ['cash', 'mobile_money', 'card', 'online']);
            $table->decimal('price_fcfa', 10, 2);

            $table->enum('status', ['pending', 'paid', 'boarded', 'cancelled', 'refunded'])
                  ->default('pending');

            $table->foreignId('sold_by')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete()
                  ->comment('Caissier — null si achat en ligne');

            $table->dateTime('purchased_at');
            $table->dateTime('boarded_at')->nullable();
            $table->text('cancellation_reason')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['departure_id', 'status']);
            $table->index('channel');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
