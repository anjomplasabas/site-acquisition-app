<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['role_id', 'department_id', 'email', 'password', 'full_name', 'status', 'force_password_change'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

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
            'force_password_change' => 'boolean',
        ];
    }

    public function isSuperAdmin(): bool
    {
        return $this->role?->name === 'SUPER_ADMIN';
    }

    public function isActive(): bool
    {
        return $this->status === 'ACTIVE';
    }

    public function isProjectManager(): bool
    {
        return $this->isLeadSaqSpecialist() || $this->isSaqSpecialist();
    }

    public function isLeadSaqSpecialist(): bool
    {
        return $this->role?->name === 'Lead SAQ Specialist';
    }

    public function isSaqSpecialist(): bool
    {
        return $this->role?->name === 'SAQ Specialist';
    }

    public function isSurveyor(): bool
    {
        return $this->role?->name === 'Surveyor';
    }

    public function isCommercialManager(): bool
    {
        return $this->role?->name === 'COMMERCIAL_MANAGER';
    }

    /**
     * Active users holding the Surveyor role — the only users eligible to be a Project's
     * PM In-charge.
     *
     * @param  Builder<User>  $query
     */
    public function scopeActiveSurveyors(Builder $query): void
    {
        $query->where('status', 'ACTIVE')->whereHas('role', fn (Builder $role) => $role->where('name', 'Surveyor'));
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Scope to users whose role holds any of the given action permissions — the shared mechanism
     * behind both "who's eligible to be assigned" dropdowns and notification recipients, so both
     * stay derived from the Roles & Permissions module instead of hardcoded role names.
     *
     * @param  string|array<int, string>  $permissions
     */
    public function scopeWithActionPermission(Builder $query, string|array $permissions): Builder
    {
        $permissions = is_array($permissions) ? $permissions : [$permissions];

        return $query->whereHas('role.actionPermissions', fn ($q) => $q->whereIn('permission', $permissions));
    }
}
