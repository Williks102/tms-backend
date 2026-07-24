<?php
// ══════════════════════════════════════════════════════════════════════════
// Runbook sécurité v3, point 4 — le scan d'embarquement ne vérifiait que le
// statut du départ du billet, jamais que ce billet correspondait au départ
// devant lequel se tient le contrôleur. Un billet valide pour un autre
// trajet passait le scan dès lors que les deux embarquements étaient
// simultanés (perte de recette invisible). Corrigé en exigeant departure_id.
// ══════════════════════════════════════════════════════════════════════════
namespace Tests\Feature;

use App\Models\Departure;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketScanTest extends TestCase
{
    use RefreshDatabase;

    public function test_refuse_un_billet_dun_autre_depart(): void
    {
        $controleur = User::factory()->create(['role' => 'controleur']);

        $departureA = Departure::factory()->create(['status' => 'boarding']);
        $departureB = Departure::factory()->create(['status' => 'boarding']);

        $ticket = Ticket::create([
            'reference'       => 'TCK-2026-000001',
            'departure_id'    => $departureA->id,
            'passenger_name'  => 'Passager A',
            'channel'         => 'physical',
            'payment_method'  => 'cash',
            'price_fcfa'      => 5000,
            'status'          => 'paid',
            'purchased_at'    => now(),
        ]);

        // Scanné devant le mauvais départ → refus explicite
        $wrongResponse = $this->actingAs($controleur)->postJson('/api/v1/tickets/scan', [
            'reference'    => $ticket->reference,
            'departure_id' => $departureB->id,
        ]);

        $wrongResponse->assertStatus(422);
        $wrongResponse->assertJsonFragment(['message' => "Ce billet n'est pas valable pour ce départ (billet émis pour le départ #{$departureA->id})"]);
        $this->assertSame('paid', $ticket->fresh()->status);

        // Scanné devant le bon départ → embarquement accepté
        $rightResponse = $this->actingAs($controleur)->postJson('/api/v1/tickets/scan', [
            'reference'    => $ticket->reference,
            'departure_id' => $departureA->id,
        ]);

        $rightResponse->assertOk();
        $this->assertSame('boarded', $ticket->fresh()->status);
    }
}
