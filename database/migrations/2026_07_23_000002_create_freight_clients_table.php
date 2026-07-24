<?php
// ══════════════════════════════════════════════════════════════════════════
// database/migrations/2026_07_23_000002_create_freight_clients_table.php
// Client fret — compte entreprise récurrent (contrairement au colis, pas
// d'expéditeur ponctuel ni de code de retrait).
// ══════════════════════════════════════════════════════════════════════════
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('freight_clients', function (Blueprint $table) {
            $table->id();
            $table->string('company_name');
            $table->string('contact_name')->nullable();
            $table->string('phone');
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('freight_clients');
    }
};
