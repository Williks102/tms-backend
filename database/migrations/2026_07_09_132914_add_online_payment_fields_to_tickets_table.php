<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->string('passenger_email')->nullable()->after('passenger_phone');

            // channel = opérateur choisi côté PaiementPro (CARD, OMCIV2, MOMOCI,
            // WAVECI, MOOVCI) — distinct de payment_method (cash|mobile_money|card|online)
            // qui reste 'online' pour tout achat en ligne, peu importe l'opérateur.
            $table->string('payment_channel')->nullable()->after('payment_method');

            // Référence opaque envoyée à PaiementPro comme referenceNumber — jamais
            // la référence publique du billet (TCK-AAAA-NNNNNN, séquentielle et donc
            // devinable). Sert à retrouver le billet depuis la notification webhook.
            $table->string('payment_token', 64)->nullable()->unique()->after('payment_channel');

            // sessionId retourné par l'init PaiementPro — utile pour le support/debug,
            // jamais utilisé pour la logique métier.
            $table->string('payment_session_id')->nullable()->after('payment_token');

            $table->dateTime('payment_confirmed_at')->nullable()->after('payment_session_id');

            // Délai de rétention du siège pendant le paiement — au-delà,
            // ExpireOnlinePendingTickets annule le billet et libère la place.
            $table->dateTime('payment_expires_at')->nullable()->after('payment_confirmed_at');

            $table->index('payment_expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropIndex(['payment_expires_at']);
            $table->dropColumn([
                'passenger_email',
                'payment_channel',
                'payment_token',
                'payment_session_id',
                'payment_confirmed_at',
                'payment_expires_at',
            ]);
        });
    }
};
