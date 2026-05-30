<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Concerns\HasTeams;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string $password
 * @property int|null $current_team_id
 * @property-read Team|null $currentTeam
 * @property-read Membership|null $pivot
 * @property-read Collection<int, ArtisanProfile> $artisanProfiles
 * @property-read CustomerProfile|null $customerProfile
 */
#[Fillable(['name', 'email', 'password', 'current_team_id'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, HasTeams, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable {
        HasTeams::teams insteadof HasRoles;
        HasRoles::teams as permissionTeams;
    }

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
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<ArtisanProfile, $this>
     */
    public function artisanProfiles(): HasMany
    {
        return $this->hasMany(ArtisanProfile::class);
    }

    /**
     * @return HasOne<CustomerProfile, $this>
     */
    public function customerProfile(): HasOne
    {
        return $this->hasOne(CustomerProfile::class);
    }

    /**
     * @return HasOne<AdminProfile, $this>
     */
    public function adminProfile(): HasOne
    {
        return $this->hasOne(AdminProfile::class);
    }

    /**
     * @return HasMany<AreaAgentAssignment, $this>
     */
    public function areaAgentAssignments(): HasMany
    {
        return $this->hasMany(AreaAgentAssignment::class);
    }

    /**
     * @return HasMany<AuditLog, $this>
     */
    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class, 'actor_id');
    }

    /**
     * @return HasMany<ArtisanProfile, $this>
     */
    public function onboardedArtisanProfiles(): HasMany
    {
        return $this->hasMany(ArtisanProfile::class, 'onboarded_by_agent_id');
    }

    /**
     * @return HasMany<ArtisanProfile, $this>
     */
    public function approvedArtisanProfiles(): HasMany
    {
        return $this->hasMany(ArtisanProfile::class, 'approved_by');
    }
}
