<?php

namespace App\Models;

use Database\Factories\CustomerFavoriteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property int $artisan_profile_id
 */
#[Fillable(['user_id', 'artisan_profile_id'])]
class CustomerFavorite extends Model
{
    /** @use HasFactory<CustomerFavoriteFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<ArtisanProfile, $this>
     */
    public function artisanProfile(): BelongsTo
    {
        return $this->belongsTo(ArtisanProfile::class);
    }
}
