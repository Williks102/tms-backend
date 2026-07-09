<?php

use App\Jobs\Drivers\AggregateMonthlyScores;
use App\Jobs\Shared\CheckDocumentExpiry;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Premier planning réel de l'app — ces deux Jobs existaient déjà (ou dans le
// cas d'AggregateMonthlyScores) mais n'étaient enregistrés nulle part, donc
// ne s'exécutaient jamais automatiquement. CheckMaintenanceThresholds reste
// volontairement absent d'ici : il est déclenché par FuelConsumptionObserver
// à chaque consommation enregistrée, pas sur un planning.
Schedule::job(new CheckDocumentExpiry)->weekly();
// Le 1er à 01h00, agrège le mois qui vient de se terminer — pas le mois en cours.
Schedule::call(fn () => dispatch(new AggregateMonthlyScores(now()->subMonthNoOverflow())))
    ->monthlyOn(1, '01:00');
