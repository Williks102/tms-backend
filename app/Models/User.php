<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\Role;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password', 'role', 'phone', 'hired_at', 'contract_type', 'contract_end_date', 'base_salary_fcfa'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => Role::class,
            'hired_at' => 'date',
            'contract_end_date' => 'date',
            'base_salary_fcfa' => 'decimal:2',
        ];
    }

    // ── Relations RH ─────────────────────────────────────────────────────

    public function leaveRequests(): MorphMany
    {
        return $this->morphMany(LeaveRequest::class, 'employable');
    }

    public function disciplinaryRecords(): MorphMany
    {
        return $this->morphMany(DisciplinaryRecord::class, 'employable');
    }

    public function hasRole(Role|string $role): bool
    {
        $role = $role instanceof Role ? $role : Role::from($role);

        return $this->role === $role;
    }

    public function hasAnyRole(array $roles): bool
    {
        foreach ($roles as $role) {
            if ($this->hasRole($role)) {
                return true;
            }
        }

        return false;
    }
}
