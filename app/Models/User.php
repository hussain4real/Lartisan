<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Concerns\HasTeams;
use App\Enums\PlatformPermission;
use App\Enums\PreferredChannel;
use App\Enums\UserStatus;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
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
 * @property string|null $phone_country_code
 * @property string|null $phone_number
 * @property string|null $phone_e164
 * @property UserStatus $status
 * @property PreferredChannel|null $preferred_channel
 * @property int|null $current_team_id
 * @property-read Team|null $currentTeam
 * @property-read Membership|null $pivot
 * @property-read Collection<int, ArtisanProfile> $artisanProfiles
 * @property-read Collection<int, Booking> $customerBookings
 * @property-read CustomerProfile|null $customerProfile
 */
#[Fillable([
    'name',
    'email',
    'password',
    'phone_country_code',
    'phone_number',
    'phone_e164',
    'phone_verified_at',
    'status',
    'preferred_channel',
    'current_team_id',
])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements FilamentUser, PasskeyUser
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
            'phone_verified_at' => 'datetime',
            'password' => 'hashed',
            'preferred_channel' => PreferredChannel::class,
            'status' => UserStatus::class,
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
     * @return HasMany<Booking, $this>
     */
    public function customerBookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'customer_id');
    }

    /**
     * @return HasMany<Address, $this>
     */
    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class);
    }

    /**
     * @return HasMany<OtpRecord, $this>
     */
    public function otpRecords(): HasMany
    {
        return $this->hasMany(OtpRecord::class);
    }

    /**
     * @return HasMany<AccountClaim, $this>
     */
    public function accountClaims(): HasMany
    {
        return $this->hasMany(AccountClaim::class);
    }

    /**
     * @return HasMany<AccountClaim, $this>
     */
    public function claimedAccountClaims(): HasMany
    {
        return $this->hasMany(AccountClaim::class, 'claimed_by');
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

    /**
     * @return HasMany<ArtisanProfile, $this>
     */
    public function suspendedArtisanProfiles(): HasMany
    {
        return $this->hasMany(ArtisanProfile::class, 'suspended_by');
    }

    /**
     * @return HasMany<KycSubmission, $this>
     */
    public function reviewedKycSubmissions(): HasMany
    {
        return $this->hasMany(KycSubmission::class, 'reviewed_by');
    }

    /**
     * @return HasMany<FieldVisit, $this>
     */
    public function fieldVisitsConducted(): HasMany
    {
        return $this->hasMany(FieldVisit::class, 'area_agent_id');
    }

    /**
     * @return HasMany<StatusHistory, $this>
     */
    public function statusHistories(): HasMany
    {
        return $this->hasMany(StatusHistory::class, 'actor_id');
    }

    /**
     * @return HasMany<BookingStatusHistory, $this>
     */
    public function bookingStatusHistories(): HasMany
    {
        return $this->hasMany(BookingStatusHistory::class, 'actor_id');
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return match ($panel->getId()) {
            'admin' => $this->can(PlatformPermission::ViewGlobalReports->value),
            'state' => $this->can(PlatformPermission::ViewStateReports->value),
            'lga' => $this->can(PlatformPermission::ViewLocalGovernmentReports->value),
            'agent' => $this->can(PlatformPermission::ViewAreaReports->value),
            default => false,
        };
    }
}
