<?php

namespace App\Http\Controllers\Hr;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Models\User;
use App\Services\Audit\ActivityLogger;
use App\Services\Export\CsvExportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EmployeeController extends Controller
{
    public function __construct(
        private readonly ActivityLogger $activityLogger,
        private readonly CsvExportService $csv,
    ) {}
    // ─────────────────────────────────────────────────────────────────────
    // GET /api/v1/hr/employees
    // Fusionne Users (staff) et Drivers en une liste unique — pas de table
    // "Employee" dédiée, chacun reste sa propre source de vérité.
    // ─────────────────────────────────────────────────────────────────────
    public function index(Request $request): JsonResponse
    {
        $users = User::all()->map(fn (User $u) => [
            'type'              => 'user',
            'id'                => $u->id,
            'name'              => $u->name,
            'contact'           => trim($u->email . ($u->phone ? " · {$u->phone}" : '')),
            'role_or_position'  => $u->role->label(),
            'status'            => null,
            'hired_at'          => $u->hired_at?->toDateString(),
            'contract_type'     => $u->contract_type,
            'contract_end_date' => $u->contract_end_date?->toDateString(),
        ]);

        $drivers = Driver::all()->map(fn (Driver $d) => [
            'type'              => 'driver',
            'id'                => $d->id,
            'name'              => $d->fullName(),
            'contact'           => $d->phone,
            'role_or_position'  => 'Chauffeur',
            'status'            => $d->status,
            'hired_at'          => $d->hired_at?->toDateString(),
            'contract_type'     => $d->contract_type,
            'contract_end_date' => $d->contract_end_date?->toDateString(),
        ]);

        $employees = $users->concat($drivers);

        if ($request->filled('search')) {
            $term = mb_strtolower($request->string('search'));
            $employees = $employees->filter(fn ($e) => str_contains(mb_strtolower($e['name']), $term));
        }

        if ($request->filled('type')) {
            $employees = $employees->where('type', $request->string('type'));
        }

        $employees = $employees->sortBy('name')->values();

        $perPage = $request->integer('per_page', 30);
        $page    = max(1, $request->integer('page', 1));
        $total   = $employees->count();

        return response()->json([
            'data'         => $employees->forPage($page, $perPage)->values(),
            'total'        => $total,
            'current_page' => $page,
            'last_page'    => (int) max(1, ceil($total / $perPage)),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // GET /api/v1/hr/employees/export — liste fusionnée Users+Drivers, CSV
    // (jamais base_salary_fcfa — réservé aux mêmes rôles que ce contrôleur,
    // mais un export téléchargé peut facilement être partagé hors de l'app).
    // ─────────────────────────────────────────────────────────────────────
    public function export(): StreamedResponse
    {
        $users = User::all()->map(fn (User $u) => [
            $u->name, $u->email, $u->role->label(), $u->phone,
            $u->hired_at?->toDateString(), $u->contract_type, $u->contract_end_date?->toDateString(),
        ]);

        $drivers = Driver::all()->map(fn (Driver $d) => [
            $d->fullName(), '—', 'Chauffeur', $d->phone,
            $d->hired_at?->toDateString(), $d->contract_type, $d->contract_end_date?->toDateString(),
        ]);

        return $this->csv->stream(
            ['Nom', 'Email', 'Rôle', 'Téléphone', "Date d'embauche", 'Type contrat', 'Fin contrat'],
            $users->concat($drivers),
            'personnel-' . now()->format('Y-m-d') . '.csv',
        );
    }

    // ─────────────────────────────────────────────────────────────────────
    // GET /api/v1/hr/employees/{type}/{id}   type = user|driver
    // ─────────────────────────────────────────────────────────────────────
    public function show(string $type, int $id): JsonResponse
    {
        if ($type === 'user') {
            $user = User::findOrFail($id);
            $user->load([
                'leaveRequests' => fn ($q) => $q->with('decidedBy')->latest('requested_at'),
                'disciplinaryRecords' => fn ($q) => $q->with('issuedBy')->latest('issued_at'),
            ]);

            return response()->json(['type' => 'user', 'employee' => $user]);
        }

        if ($type === 'driver') {
            $driver = Driver::findOrFail($id);
            $driver->load([
                'leaveRequests' => fn ($q) => $q->with('decidedBy')->latest('requested_at'),
                'disciplinaryRecords' => fn ($q) => $q->with('issuedBy')->latest('issued_at'),
                'documents',
            ]);

            return response()->json(['type' => 'driver', 'employee' => $driver]);
        }

        abort(404, "Type d'employé inconnu: {$type}");
    }

    // ─────────────────────────────────────────────────────────────────────
    // POST /api/v1/hr/employees — créer un membre du personnel (manager, rh)
    // Mot de passe généré aléatoirement, renvoyé UNE SEULE FOIS dans cette
    // réponse — jamais stocké en clair, jamais renvoyé ailleurs ensuite.
    // ─────────────────────────────────────────────────────────────────────
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'              => 'required|string|max:150',
            'email'             => 'required|email|unique:users,email',
            'role'              => 'required|in:' . implode(',', array_column(Role::cases(), 'value')),
            'phone'             => 'nullable|string|max:20',
            'hired_at'          => 'nullable|date',
            'contract_type'     => 'nullable|string|max:50',
            'contract_end_date' => 'nullable|date',
            'base_salary_fcfa'  => 'nullable|numeric|min:0',
            // Quotient familial ITS — 1 = célibataire sans enfant (défaut du
            // modèle si omis). Voir Services/Accounting/PayrollTaxService.
            'parts_fiscales'    => 'nullable|numeric|min:1|max:99.9',
        ]);

        $password = Str::password(12);

        $user = User::create([...$data, 'password' => $password]);

        $this->activityLogger->log(
            'employee.created',
            $user,
            "Personnel créé: {$user->name} ({$user->role->value})",
            userId: $request->user()->id,
        );

        return response()->json([
            'message'            => 'Membre du personnel créé avec succès',
            'user'               => $user,
            'generated_password' => $password,
        ], 201);
    }

    // ─────────────────────────────────────────────────────────────────────
    // POST /api/v1/hr/employees/import — création en masse depuis un CSV
    // (manager, rh). Colonnes attendues (en-tête) : name,email,role,phone,
    // hired_at,contract_type,contract_end_date,base_salary_fcfa — seules
    // name/email/role sont obligatoires. Chaque ligne est traitée
    // indépendamment (pas de tout-ou-rien) : une ligne invalide est
    // rapportée dans "failed" sans bloquer les autres.
    // ─────────────────────────────────────────────────────────────────────
    public function import(Request $request): JsonResponse
    {
        $request->validate(['file' => 'required|file|mimes:csv,txt']);

        $handle = fopen($request->file('file')->getRealPath(), 'r');
        $header = array_map(fn ($h) => strtolower(trim($h)), fgetcsv($handle) ?: []);
        $validRoles = array_column(Role::cases(), 'value');

        $created = [];
        $failed  = [];
        $rowNum  = 1; // ligne 1 = en-tête

        while (($row = fgetcsv($handle)) !== false) {
            $rowNum++;
            // array_pad seul ne raccourcit pas une ligne plus longue que
            // l'en-tête (array_combine exigerait alors des tailles égales) —
            // on tronque d'abord, puis on complète les colonnes manquantes.
            $row     = array_pad(array_slice($row, 0, count($header)), count($header), null);
            $rowData = array_combine($header, $row);
            // Colonne absente de l'en-tête (CSV avec moins de colonnes que
            // prévu) OU valeur vide → null, jamais une "undefined array key".
            $col = fn (string $key) => (($rowData[$key] ?? null) !== null && $rowData[$key] !== '')
                ? $rowData[$key] : null;

            try {
                $name  = trim((string) $col('name'));
                $email = trim((string) $col('email'));
                $role  = trim((string) $col('role'));

                if (!$name || !$email || !$role) {
                    throw new \Exception('Les colonnes name, email et role sont obligatoires');
                }
                if (!in_array($role, $validRoles, true)) {
                    throw new \Exception("Rôle inconnu: {$role}");
                }
                if (User::where('email', $email)->exists()) {
                    throw new \Exception("Email déjà utilisé: {$email}");
                }

                $password = Str::password(12);

                $user = User::create([
                    'name'              => $name,
                    'email'             => $email,
                    'role'              => $role,
                    'password'          => $password,
                    'phone'             => $col('phone'),
                    'hired_at'          => $col('hired_at'),
                    'contract_type'     => $col('contract_type'),
                    'contract_end_date' => $col('contract_end_date'),
                    'base_salary_fcfa'  => $col('base_salary_fcfa'),
                ]);

                $created[] = ['name' => $user->name, 'email' => $user->email, 'generated_password' => $password];
            } catch (\Exception $e) {
                $failed[] = ['row' => $rowNum, 'email' => $rowData['email'] ?? null, 'reason' => $e->getMessage()];
            }
        }

        fclose($handle);

        $this->activityLogger->log(
            'employee.imported',
            null,
            'Import CSV personnel: ' . count($created) . ' créé(s), ' . count($failed) . ' échec(s)',
            metadata: ['created_emails' => array_column($created, 'email'), 'failed_rows' => array_column($failed, 'row')],
            userId: $request->user()->id,
        );

        return response()->json([
            'message' => count($created) . ' créé(s), ' . count($failed) . ' échec(s)',
            'created' => $created,
            'failed'  => $failed,
        ]);
    }
}
