<?php

namespace App\Models;

use App\Enums\BookingStatus;
use Database\Factories\BookingStatusHistoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $booking_id
 * @property int|null $actor_id
 * @property BookingStatus|null $from_status
 * @property BookingStatus $to_status
 * @property string|null $notes
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 */
#[Fillable([
    'booking_id',
    'actor_id',
    'from_status',
    'to_status',
    'notes',
    'metadata',
    'created_at',
])]
class BookingStatusHistory extends Model
{
    /** @use HasFactory<BookingStatusHistoryFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    /**
     * @return BelongsTo<Booking, $this>
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'from_status' => BookingStatus::class,
            'metadata' => 'array',
            'to_status' => BookingStatus::class,
        ];
    }
}
