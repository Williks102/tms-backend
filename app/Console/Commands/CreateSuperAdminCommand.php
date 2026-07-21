<?php
// ══════════════════════════════════════════════════════════════════════════
// app/Console/Commands/CreateSuperAdminCommand.php
//
// Crée le compte Role::SUPER_ADMIN de CE déploiement — jamais fait via
// UserSeeder (pas de seeding automatique pour un client), jamais via
// EmployeeController (rôle explicitement exclu, voir Enums\Role et
// EmployeeController::assignableRoleValues()). Un seul compte par
// déploiement suffit — voir runbook-nouveau-client.md.
//
//   php artisan tms:create-super-admin --email=vous@example.com --name="Votre nom"
// ══════════════════════════════════════════════════════════════════════════
namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class CreateSuperAdminCommand extends Command
{
    protected $signature = 'tms:create-super-admin {--email=} {--name=}';

    protected $description = 'Crée le compte super admin de ce déploiement (accès facturation/abonnement uniquement)';

    public function handle(): int
    {
        $email = $this->option('email') ?: $this->ask('Email du compte super admin');
        $name  = $this->option('name') ?: $this->ask('Nom', 'Super Admin');

        if (User::where('email', $email)->exists()) {
            $this->error("Un compte existe déjà avec l'email {$email}.");

            return self::FAILURE;
        }

        $password = Str::password(16);

        User::create([
            'name'     => $name,
            'email'    => $email,
            'password' => $password,
            'role'     => Role::SUPER_ADMIN,
        ]);

        $this->info('Compte super admin créé.');
        $this->newLine();
        $this->line("Email        : {$email}");
        $this->line("Mot de passe : {$password}");
        $this->newLine();
        $this->warn('Ce mot de passe ne sera plus jamais affiché — notez-le maintenant. Connexion via /login comme n\'importe quel autre rôle.');

        return self::SUCCESS;
    }
}
