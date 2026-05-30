<?php

namespace App\Models;

use App\Enums\PaymentProviderName;
use App\Enums\ProviderWebhookEventStatus;
use Database\Factories\ProviderWebhookEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property PaymentProviderName $provider
 * @property string $event
 * @property string|null $provider_event_id
 * @property string|null $reference
 * @property array<string, mixed> $payload
 * @property string|null $signature
 * @property Carbon $received_at
 * @property Carbon|null $processed_at
 * @property ProviderWebhookEventStatus $status
 * @property string|null $failure_reason
 */
#[Fillable([
    'provider',
    'event',
    'provider_event_id',
    'reference',
    'payload',
    'signature',
    'received_at',
    'processed_at',
    'status',
    'failure_reason',
])]
class ProviderWebhookEvent extends Model
{
    /** @use HasFactory<ProviderWebhookEventFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'processed_at' => 'datetime',
            'provider' => PaymentProviderName::class,
            'received_at' => 'datetime',
            'status' => ProviderWebhookEventStatus::class,
        ];
    }
}
