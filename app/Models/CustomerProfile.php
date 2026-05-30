<?php

namespace App\Models;

use App\Enums\PlatformPermission;
use Database\Factories\CustomerProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property int|null $default_address_id
 * @property array<string, mixed>|null $preferences
 */
#[Fillable(['user_id', 'default_address_id', 'preferences'])]
class CustomerProfile extends Model
{
    /** @use HasFactory<CustomerProfileFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Address, $this>
     */
    public function defaultAddress(): BelongsTo
    {
        return $this->belongsTo(Address::class, 'default_address_id');
    }

    /**
     * @param  Builder<CustomerProfile>  $query
     */
    public function scopeOwnedBy(Builder $query, User $user): void
    {
        $query->where('user_id', $user->id);
    }

    /**
     * @param  Builder<CustomerProfile>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if ($user->can(PlatformPermission::ViewGlobalReports->value)) {
            return;
        }

        $query->ownedBy($user);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'preferences' => 'array',
        ];
    }
}
