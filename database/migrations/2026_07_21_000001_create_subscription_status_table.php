<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Table singleton (une seule ligne, id=1) — statut d'abonnement SaaS de ce
// déploiement client. Voir App\Models\SubscriptionStatus et
// App\Http\Middleware\EnsureSubscriptionActive. Chaque compagnie cliente a
// son propre déploiement isolé (runbook-nouveau-client.md), donc ce statut
// est propre à CE client uniquement — pas de notion multi-tenant ici.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_status', function (Blueprint $table) {
            $table->id();
            $table->enum('status', ['active', 'suspended'])->default('active');
            $table->date('paid_until')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_status');
    }
};
