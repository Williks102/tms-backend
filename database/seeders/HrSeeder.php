<?php
// ══════════════════════════════════════════════════════════════════════════
// database/seeders/HrSeeder.php
// ══════════════════════════════════════════════════════════════════════════
namespace Database\Seeders;

use App\Models\DisciplinaryRecord;
use App\Models\Driver;
use App\Models\LeaveRequest;
use App\Models\User;
use Illuminate\Database\Seeder;

class HrSeeder extends Seeder
{
    public function run(): void
    {
        $manager = User::where('email', 'manager@tms-ci.com')->first();
        $rh      = User::where('email', 'rh@tms-ci.com')->first();

        // Complète les infos contrat (ajoutées après le seeding initial des users/drivers).
        User::where('email', 'dg@tms-ci.com')->update(['contract_type' => 'cdi', 'base_salary_fcfa' => 1800000]);
        User::where('email', 'manager@tms-ci.com')->update(['contract_type' => 'cdi', 'base_salary_fcfa' => 950000]);
        User::where('email', 'dispatcher@tms-ci.com')->update(['contract_type' => 'cdi', 'base_salary_fcfa' => 400000]);
        User::where('email', 'rh@tms-ci.com')->update(['contract_type' => 'cdi', 'base_salary_fcfa' => 450000]);
        User::where('email', 'caissier@tms-ci.com')->update([
            'contract_type'     => 'cdd',
            'contract_end_date' => now()->addDays(20), // démo: contrat bientôt expiré
            'base_salary_fcfa'  => 250000,
        ]);

        Driver::query()->update(['contract_type' => 'cdi', 'base_salary_fcfa' => 220000]);
        // Un chauffeur en CDD dont le contrat expire bientôt, pour la démo du tableau de bord RH.
        Driver::where('employee_number', 'CH-2024-008')->update([
            'contract_type'     => 'cdd',
            'contract_end_date' => now()->addDays(15),
        ]);

        if (!$manager || !$rh) {
            $this->command->warn('  Utilisateurs manager/rh introuvables — HrSeeder ignoré');
            return;
        }

        $drivers = Driver::orderBy('id')->get();

        $leaves = [
            [
                'employable'   => $drivers[0] ?? null,
                'type'         => 'conge_paye',
                'start_date'   => now()->subDays(10),
                'end_date'     => now()->subDays(4),
                'reason'       => 'Congés annuels',
                'status'       => 'approved',
                'decided_by'   => $manager->id,
            ],
            [
                'employable'   => $drivers[2] ?? null,
                'type'         => 'maladie',
                'start_date'   => now()->subDays(1),
                'end_date'     => now()->addDays(2),
                'reason'       => 'Grippe, certificat médical fourni',
                'status'       => 'approved',
                'decided_by'   => $rh->id,
            ],
            [
                'employable'   => $drivers[5] ?? null,
                'type'         => 'conge_paye',
                'start_date'   => now()->addDays(5),
                'end_date'     => now()->addDays(12),
                'reason'       => 'Congés annuels — période Tabaski',
                'status'       => 'pending',
            ],
            [
                'employable'   => $drivers[6] ?? null,
                'type'         => 'sans_solde',
                'start_date'   => now()->addDays(3),
                'end_date'     => now()->addDays(5),
                'reason'       => 'Convenance personnelle',
                'status'       => 'rejected',
                'decided_by'   => $manager->id,
                'decision_notes' => 'Effectif insuffisant sur la période demandée',
            ],
        ];

        $count = 0;
        foreach ($leaves as $data) {
            if (!$data['employable']) continue;

            LeaveRequest::create([
                'employable_type' => $data['employable']::class,
                'employable_id'   => $data['employable']->id,
                'type'            => $data['type'],
                'start_date'      => $data['start_date'],
                'end_date'        => $data['end_date'],
                'reason'          => $data['reason'],
                'status'          => $data['status'],
                'requested_at'    => $data['start_date']->copy()->subDays(3),
                'decided_by'      => $data['decided_by'] ?? null,
                'decided_at'      => isset($data['decided_by']) ? $data['start_date']->copy()->subDays(2) : null,
                'decision_notes'  => $data['decision_notes'] ?? null,
            ]);
            $count++;
        }
        $this->command->info("  Congés créés: {$count}");

        $disciplinary = [
            [
                'employable'  => $drivers[3] ?? null,
                'type'        => 'avertissement_verbal',
                'description' => 'Retard répété au dépôt le matin (3 fois en un mois).',
                'issued_at'   => now()->subDays(20),
            ],
            [
                'employable'  => $drivers[6] ?? null,
                'type'        => 'avertissement_ecrit',
                'description' => 'Excès de vitesse constaté sur la ligne ABJ-BKE — signalé par incident INC.',
                'issued_at'   => now()->subDays(8),
            ],
        ];

        $dCount = 0;
        foreach ($disciplinary as $data) {
            if (!$data['employable']) continue;

            DisciplinaryRecord::create([
                'employable_type' => $data['employable']::class,
                'employable_id'   => $data['employable']->id,
                'type'            => $data['type'],
                'description'     => $data['description'],
                'issued_by'       => $rh->id,
                'issued_at'       => $data['issued_at'],
            ]);
            $dCount++;
        }
        $this->command->info("  Enregistrements disciplinaires créés: {$dCount}");
    }
}
