<?php

namespace App\Actions\Notifications;

use App\Contracts\Notifications\NotificationProvider;
use App\Enums\NotificationChannel;
use App\Enums\NotificationDeliveryStatus;
use App\Enums\NotificationEventType;
use App\Models\NotificationDelivery;
use App\Services\Notifications\MailNotificationProvider;
use App\Services\Notifications\WhatsappNotificationProvider;
use App\Support\ProviderFailureLogger;
use Illuminate\Database\Eloquent\Model;
use Throwable;

class SendNotificationDelivery
{
    public function __construct(
        private readonly ProviderFailureLogger $providerFailureLogger,
    ) {}

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function handle(
        NotificationEventType $eventType,
        NotificationChannel $channel,
        string $recipientAddress,
        string $subject,
        string $body,
        ?Model $recipient = null,
        ?Model $source = null,
        ?string $recipientName = null,
        ?string $dedupeKey = null,
        array $metadata = [],
    ): NotificationDelivery {
        if ($dedupeKey !== null) {
            $existing = NotificationDelivery::query()
                ->where('dedupe_key', $dedupeKey)
                ->where('channel', $channel->value)
                ->where('recipient_address', $recipientAddress)
                ->first();

            if ($existing instanceof NotificationDelivery) {
                return $existing;
            }
        }

        $delivery = NotificationDelivery::query()->create([
            'body' => $body,
            'channel' => $channel,
            'dedupe_key' => $dedupeKey,
            'event_type' => $eventType,
            'metadata' => $metadata,
            'recipient_address' => $recipientAddress,
            'recipient_id' => $recipient?->getKey(),
            'recipient_name' => $recipientName,
            'recipient_type' => $recipient?->getMorphClass(),
            'source_id' => $source?->getKey(),
            'source_type' => $source?->getMorphClass(),
            'status' => NotificationDeliveryStatus::Pending,
            'subject' => $subject,
        ]);

        return $this->send($delivery);
    }

    public function retry(NotificationDelivery $delivery): NotificationDelivery
    {
        return $this->send($delivery);
    }

    public function markDeadLetter(NotificationDelivery $delivery, string $reason): NotificationDelivery
    {
        $delivery->forceFill([
            'dead_lettered_at' => now(),
            'failure_reason' => $reason,
            'status' => NotificationDeliveryStatus::DeadLettered,
        ])->save();

        return $delivery->refresh();
    }

    private function send(NotificationDelivery $delivery): NotificationDelivery
    {
        $provider = $this->providerFor($delivery->channel);

        if (! $provider->enabled()) {
            $delivery->forceFill([
                'failure_reason' => $provider->providerName().' provider is disabled or not configured.',
                'provider' => $provider->providerName(),
                'status' => NotificationDeliveryStatus::Skipped,
            ])->save();

            return $delivery->refresh();
        }

        try {
            $receipt = $provider->send($delivery);

            $delivery->forceFill([
                'attempts' => $delivery->attempts + 1,
                'failed_at' => null,
                'failure_reason' => null,
                'last_attempted_at' => now(),
                'metadata' => [
                    ...($delivery->metadata ?? []),
                    'provider_receipt' => $receipt->metadata,
                ],
                'provider' => $provider->providerName(),
                'provider_message_id' => $receipt->messageId,
                'provider_status' => $receipt->status,
                'sent_at' => now(),
                'status' => NotificationDeliveryStatus::Sent,
            ])->save();
        } catch (Throwable $throwable) {
            $delivery->forceFill([
                'attempts' => $delivery->attempts + 1,
                'failed_at' => now(),
                'failure_reason' => $throwable->getMessage(),
                'last_attempted_at' => now(),
                'provider' => $provider->providerName(),
                'status' => NotificationDeliveryStatus::Failed,
            ])->save();

            $this->providerFailureLogger->report($provider->providerName(), 'notification-send', $throwable, [
                'delivery_id' => $delivery->id,
                'event_type' => $delivery->event_type->value,
            ]);
        }

        return $delivery->refresh();
    }

    private function providerFor(NotificationChannel $channel): NotificationProvider
    {
        return match ($channel) {
            NotificationChannel::Email => app(MailNotificationProvider::class),
            NotificationChannel::Whatsapp => app(WhatsappNotificationProvider::class),
        };
    }
}
