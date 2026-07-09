<?php
// ══════════════════════════════════════════════════════════════════════════
// Barème CNPS/ITS — éditable (POST/PUT /comptabilite/payroll-tax-brackets),
// pas codé en dur, pour que le comptable puisse corriger un taux sans
// déploiement quand la loi change. Voir PayrollTaxBracketSeeder pour les
// valeurs de départ et l'avertissement complet sur leur fiabilité.
// ══════════════════════════════════════════════════════════════════════════
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_tax_brackets', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['cnps', 'its']);
            $table->decimal('min_fcfa', 12, 2);
            // null = pas de plafond (dernière tranche ITS, taux marginal
            // appliqué indéfiniment) — pour CNPS, la dernière tranche a
            // toujours un max_fcfa non-null (plafond de cotisation).
            $table->decimal('max_fcfa', 12, 2)->nullable();
            $table->decimal('rate_percent', 5, 2);
            $table->string('label');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_tax_brackets');
    }
};
