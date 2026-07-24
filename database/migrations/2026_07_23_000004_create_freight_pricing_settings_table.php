<?php
// ══════════════════════════════════════════════════════════════════════════
// Réglage tarifaire fret — singleton (une seule ligne), même pattern que
// subscription_status. Rend RATE_PER_TON_KM_FCFA/MINIMUM_FEE_FCFA éditables
// sans déploiement (FreightPricingSettingController, role:manager en écriture)
// plutôt que des constantes figées dans FreightPricingService.
// ══════════════════════════════════════════════════════════════════════════
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('freight_pricing_settings', function (Blueprint $table) {
            $table->id();
            $table->decimal('rate_per_ton_km_fcfa', 10, 2)->default(150);
            $table->decimal('minimum_fee_fcfa', 12, 2)->default(15000);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('freight_pricing_settings');
    }
};
