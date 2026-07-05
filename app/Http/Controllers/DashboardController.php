<?php
// ══════════════════════════════════════════════════════════════════════════
// app/Http/Controllers/DashboardController.php
// Endpoint unique qui alimente le tableau de bord Next.js en polling
// ══════════════════════════════════════════════════════════════════════════
namespace App\Http\Controllers;

use App\Models\Departure;
use App\Models\Driver;
use App\Models\FuelVoucher;
use App\Models\SystemAlert;
use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    // ─────────────────────────────────────────────────────────────────────
    // GET /api/v1/dashboard/live
    // Polling toutes les 8–10s depuis Next.js
    // Cache de 5s pour éviter de surcharger la BDD
    // ─────────────────────────────────────────────────────────────────────
    public function live(): JsonResponse
    {
        $data = Cache::remember('dashboard:live', 5, function () {
            return [
                'fleet'     => $this->fleetStatus(),
                'drivers'   => $this->driversStatus(),
                'alerts'    => $this->alertsSummary(),
                'finance'   => $this->financeSummary(),
                'departures'=> $this->liveDepartures(),
                'generated_at' => now()->toIso8601String(),
            ];
        });

        return response()->json($data);
    }

    // ─────────────────────────────────────────────────────────────────────
    // GET /api/v1/dashboard/rentabilite?date=2025-03-15
    // ─────────────────────────────────────────────────────────────────────
    public function rentabilite(Request $request): JsonResponse
    {
        $date = $request->filled('date')
            ? Carbon::parse($request->date)
            : today();

        // Agrégation: revenus par ligne ce jour
        // En prod: jointure avec la table tickets/billets (ClicBillet)
        // Ici on simule depuis les départs du jour
        $departures = Departure::with(['route'])
            ->forDate($date)
            ->notCancelled()
            ->whereNotNull('vehicle_id')
            ->get();

        $lines = $departures->groupBy('route_id')->map(function ($deps, $routeId) {
            $route = $deps->first()->route;

            // Revenus estimés: places × tarif (en prod: sum tickets vendus)
            $estimatedRevenue = $deps->sum(function ($d) {
                return ($d->vehicle?->capacity ?? 0) * $d->route->base_fare;
            });

            // Coût carburant réel (depuis fuel_consumption_logs)
            $fuelCost = DB::table('fuel_consumption_logs')
                ->join('fuel_vouchers', 'fuel_consumption_logs.fuel_voucher_id', '=', 'fuel_vouchers.id')
                ->whereIn('fuel_consumption_logs.departure_id', $deps->pluck('id'))
                ->sum('fuel_vouchers.total_cost');

            // Coût chauffeur estimé (salaire journalier / nb départs)
            $driverCost = $deps->count() * 15000; // 15 000 FCFA par voyage (à paramétrer)

            // Péage estimé depuis la distance
            $tollCost = $deps->sum(fn($d) => $d->route->distance_km * 15); // ~15 FCFA/km

            $netProfit = $estimatedRevenue - $fuelCost - $driverCost - $tollCost;

            return [
                'route_id'          => $routeId,
                'route_code'        => $route->code,
                'route_name'        => $route->name,
                'departures_count'  => $deps->count(),
                'revenue_fcfa'      => round($estimatedRevenue),
                'fuel_cost_fcfa'    => round($fuelCost),
                'driver_cost_fcfa'  => round($driverCost),
                'toll_cost_fcfa'    => round($tollCost),
                'net_profit_fcfa'   => round($netProfit),
                'margin_percent'    => $estimatedRevenue > 0
                    ? round(($netProfit / $estimatedRevenue) * 100, 1)
                    : 0,
            ];
        })->values();

        return response()->json([
            'date'   => $date->toDateString(),
            'lines'  => $lines,
            'totals' => [
                'revenue_fcfa'    => $lines->sum('revenue_fcfa'),
                'fuel_cost_fcfa'  => $lines->sum('fuel_cost_fcfa'),
                'net_profit_fcfa' => $lines->sum('net_profit_fcfa'),
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // GET /api/v1/dashboard/weekly
    // ─────────────────────────────────────────────────────────────────────
    public function weekly(): JsonResponse
    {
        $data = Cache::remember('dashboard:weekly', 300, function () { // Cache 5min
            $days = collect();

            for ($i = 6; $i >= 0; $i--) {
                $date = today()->subDays($i);

                $departures = Departure::forDate($date)->notCancelled()->count();
                $fuelCost   = DB::table('fuel_vouchers')
                    ->whereDate('approved_at', $date)
                    ->where('status', 'approved')
                    ->sum('total_cost');

                $days->push([
                    'date'            => $date->toDateString(),
                    'label'           => $date->locale('fr')->isoFormat('ddd'),
                    'departures'      => $departures,
                    'fuel_cost_fcfa'  => round($fuelCost),
                    // revenue_fcfa: en prod depuis l'API ClicBillet
                ]);
            }

            return $days;
        });

        return response()->json(['days' => $data]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Méthodes privées — blocs du /live
    // ─────────────────────────────────────────────────────────────────────

    private function fleetStatus(): array
    {
        $counts = Vehicle::query()
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        return [
            'on_trip'     => $counts->get('on_trip', 0),
            'boarding'    => $counts->get('boarding', 0),
            'available'   => $counts->get('available', 0),
            'maintenance' => $counts->get('maintenance', 0),
            'inactive'    => $counts->get('inactive', 0),
            'total'       => $counts->sum(),
        ];
    }

    private function driversStatus(): array
    {
        $counts = Driver::query()
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        return [
            'on_duty'   => $counts->get('on_duty', 0),
            'available' => $counts->get('available', 0),
            'resting'   => $counts->get('resting', 0),
            'on_leave'  => $counts->get('on_leave', 0),
        ];
    }

    private function alertsSummary(): array
    {
        $alerts = SystemAlert::active()
            ->selectRaw('severity, COUNT(*) as count')
            ->groupBy('severity')
            ->pluck('count', 'severity');

        return [
            'critical' => $alerts->get('critical', 0),
            'warning'  => $alerts->get('warning', 0),
            'info'     => $alerts->get('info', 0),
            'total'    => $alerts->sum(),
            // Les 5 plus récentes pour affichage immédiat
            'latest'   => SystemAlert::active()
                ->latest()
                ->limit(5)
                ->get(['id', 'type', 'severity', 'message', 'entity_type', 'entity_id', 'created_at']),
        ];
    }

    private function financeSummary(): array
    {
        // Bons approuvés aujourd'hui
        $fuelCostToday = FuelVoucher::where('status', 'approved')
            ->whereDate('approved_at', today())
            ->sum('total_cost');

        // Bons en attente (risque financier)
        $pendingVouchers = FuelVoucher::where('status', 'pending')->count();

        return [
            'fuel_cost_today_fcfa' => round($fuelCostToday),
            'pending_vouchers'     => $pendingVouchers,
            // revenue_today_fcfa: à connecter à l'API ClicBillet
            // Pour l'instant: estimation depuis départs × tarif moyen
            'revenue_today_fcfa'   => $this->estimateRevenueToday(),
        ];
    }

    private function liveDepartures(): \Illuminate\Support\Collection
    {
        return Departure::with(['route:id,code,name,origin_city,destination_city', 'vehicle:id,plate_number,model', 'driver:id,first_name,last_name', 'gate:id,gate_code'])
            ->whereIn('status', ['boarding', 'departed'])
            ->orderBy('departure_datetime')
            ->get()
            ->map(fn(Departure $d) => [
                'id'                 => $d->id,
                'route'              => $d->route?->name,
                'route_code'         => $d->route?->code,
                'vehicle'            => $d->vehicle?->plate_number,
                'driver'             => $d->driver?->fullName(),
                'status'             => $d->status,
                'boarding_gate'      => $d->boarding_gate,
                'departure_datetime' => $d->departure_datetime?->toIso8601String(),
                'estimated_arrival'  => $d->estimated_arrival?->toIso8601String(),
                'actual_departure'   => $d->actual_departure?->toIso8601String(),
                'delay_minutes'      => $d->delayMinutes(),
            ]);
    }

    private function estimateRevenueToday(): float
    {
        return (float) Departure::with('route:id,base_fare')
            ->forDate(today())
            ->where('departures.status', '!=', 'cancelled') // Spécifier la table
            ->whereNotNull('vehicle_id')
            ->join('vehicles', 'departures.vehicle_id', '=', 'vehicles.id')
            ->join('routes', 'departures.route_id', '=', 'routes.id')
            ->selectRaw('SUM(vehicles.capacity * routes.base_fare) as total')
            ->value('total') ?? 0;
    }
}
