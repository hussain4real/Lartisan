<?php

namespace App\Models;

use App\Enums\NotificationChannel;
use App\Enums\NotificationDeliveryStatus;
use App\Enums\NotificationEventType;
use Database\Factories\NotificationDeliveryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property NotificationEventType $event_type
 * @property NotificationChannel $channel
 * @property NotificationDeliveryStatus $status
 * @property string|null $recipient_type
 * @property int|null $recipient_id
 * @property string|null $recipient_name
 * @property string $recipient_address
 * @property string|null $source_type
 * @property int|null $source_id
 * @property string $subject
 * @property string $body
 * @property string|null $provider
 * @property string|null $provider_message_id
 * @property string|null $provider_status
 * @property string|null $dedupe_key
 * @property int $attempts
 * @property Carbon|null $last_attempted_at
 * @property Carbon|null $sent_at
 * @property Carbon|null $failed_at
 * @property Carbon|null $dead_lettered_at
 * @property Carbon|null $callback_received_at
 * @property string|null $failure_reason
 * @property array<string, mixed>|null $metadata
 */
#[Fillable([
    'event_type',
    'channel',
    'status',
    'recipient_type',
    'recipient_id',
    'recipient_name',
    'recipient_address',
    'source_type',
    'source_id',
    'subject',
    'body',
    'provider',
    'provider_message_id',
    'provider_status',
    'dedupe_key',
    'attempts',
    'last_attempted_at',
    'sent_at',
    'failed_at',
    'dead_lettered_at',
    'callback_received_at',
    'failure_reason',
    'metadata',
])]
class NotificationDelivery extends Model
{
    /** @use HasFactory<NotificationDeliveryFactory> */
    use HasFactory;

    /**
     * @return MorphTo<Model, $this>
     */
    public function recipient(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'callback_received_at' => 'datetime',
            'channel' => NotificationChannel::class,
            'dead_lettered_at' => 'datetime',
            'event_type' => NotificationEventType::class,
            'failed_at' => 'datetime',
            'last_attempted_at' => 'datetime',
            'metadata' => 'array',
            'sent_at' => 'datetime',
            'status' => NotificationDeliveryStatus::class,
        ];
    }
}
