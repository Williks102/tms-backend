<?php
// ══════════════════════════════════════════════════════════════════════════
// app/Models/Driver.php
// ══════════════════════════════════════════════════════════════════════════
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Driver extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'employee_number', 'first_name', 'last_name', 'phone',
        'license_number', 'license_category',
        'license_expires_at', 'medical_expires_at',
        'hired_at', 'status', 'user_id',
        'contract_type', 'contract_end_date', 'base_salary_fcfa', 'parts_fiscales',
    ];

    // Masqués par défaut de toute sérialisation JSON — GET /drivers et
    // /drivers/{id} sont accessibles à tout rôle authentifié (lecture ouverte,
    // comme le reste du module planning), mais le salaire ne doit être visible
    // que par manager/rh, comme pour le module HR. Voir
    // DriverController::canViewSalary() qui les rend visibles au cas par cas.
    protected $hidden = ['base_salary_fcfa', 'parts_fiscales'];

    protected $casts = [
        'license_expires_at' => 'date',
        'medical_expires_at' => 'date',
        'hired_at'           => 'date',
        'contract_end_date'  => 'date',
        'base_salary_fcfa'   => 'decimal:2',
        'parts_fiscales'     => 'decimal:1',
    ];

    // ── Relations ──────────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function departures(): HasMany
    {
        return $this->hasMany(Departure::class);
    }

    public function restLogs(): HasMany
    {
        return $this->hasMany(DriverRestLog::class)->latest('duty_start');
    }

    public function tripStats(): HasMany
    {
        return $this->hasMany(DriverTripStats::class)->latest('recorded_at');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(DriverDocument::class);
    }

    public function monthlyScores(): HasMany
    {
        return $this->hasMany(DriverMonthlyScore::class)->latest('month');
    }

    public function leaveRequests(): MorphMany
    {
        return $this->morphMany(LeaveRequest::class, 'employable');
    }

    public function disciplinaryRecords(): MorphMany
    {
        return $this->morphMany(DisciplinaryRecord::class, 'employable');
    }

    // ── Scopes ─────────────────────────────────────────────────────────

    public function scopeAvailable($query)
    {
        return $query->where('status', 'available');
    }

    // ── Helpers ────────────────────────────────────────────────────────

    public function fullName(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    public function isAvailable(): bool
    {
        return $this->status === 'available';
    }

    public function licenseExpiresInDays(): int
    {
        return (int) now()->diffInDays($this->license_expires_at, false);
    }

    public function medicalExpiresInDays(): int
    {
        return (int) now()->diffInDays($this->medical_expires_at, false);
    }

    public function hasExpiredDocuments(): bool
    {
        return $this->licenseExpiresInDays() <= 0
            || $this->medicalExpiresInDays() <= 0;
    }
}




