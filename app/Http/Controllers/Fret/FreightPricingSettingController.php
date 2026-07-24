<?php
// ══════════════════════════════════════════════════════════════════════════
// app/Http/Controllers/Fret/FreightPricingSettingController.php
// Réglage du tarif fret (singleton) — voir FreightPricingSetting/
// FreightPricingService. Lecture ouverte au groupe fret (manager,agent_fret),
// écriture réservée manager (décision tarifaire, voir routes/api.php).
// ══════════════════════════════════════════════════════════════════════════
namespace App\Http\Controllers\Fret;

use App\Http\Controllers\Controller;
use App\Models\FreightPricingSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FreightPricingSettingController extends Controller
{
    // GET /api/v1/fret/pricing-settings
    public function index(): JsonResponse
    {
        return response()->json(['settings' => FreightPricingSetting::current()]);
    }

    // PUT /api/v1/fret/pricing-settings
    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'rate_per_ton_km_fcfa' => 'required|numeric|min:0',
            'minimum_fee_fcfa'     => 'required|numeric|min:0',
        ]);

        $settings = FreightPricingSetting::current();
        $settings->update($data);

        return response()->json(['message' => 'Tarif fret mis à jour', 'settings' => $settings->fresh()]);
    }
}
