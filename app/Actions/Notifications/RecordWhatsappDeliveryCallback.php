<?php

namespace App\Actions\Notifications;

use App\Enums\NotificationChannel;
use App\Enums\NotificationDeliveryStatus;
use App\Enums\NotificationEventType;
use App\Models\NotificationDelivery;

class RecordWhatsappDeliveryCallback
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(array $payload): NotificationDelivery
    {
        $messageId = $this->stringValue($payload['message_id'] ?? $payload['id'] ?? null);
        $providerStatus = $this->stringValue($payload['status'] ?? null) ?? 'callback_received';
        $delivery = $messageId === null
            ? null
            : NotificationDelivery::query()
                ->where('provider', 'whatsapp')
                ->where('provider_message_id', $messageId)
                ->first();

        if (! $delivery instanceof NotificationDelivery) {
            $delivery = NotificationDelivery::query()->create([
                'body' => 'WhatsApp provider callback received.',
                'channel' => NotificationChannel::Whatsapp,
                'event_type' => NotificationEventType::ProviderCallback,
                'metadata' => ['callback' => $payload],
                'provider' => 'whatsapp',
                'provider_message_id' => $messageId,
                'provider_status' => $providerStatus,
                'recipient_address' => $this->stringValue($payload['recipient'] ?? null) ?? 'unknown',
                'status' => $this->statusFor($providerStatus),
                'subject' => 'WhatsApp provider callback',
            ]);
        }

        $delivery->forceFill([
            'callback_received_at' => now(),
            'metadata' => [
                ...($delivery->metadata ?? []),
                'callback' => $payload,
            ],
            'provider_status' => $providerStatus,
            'status' => $this->statusFor($providerStatus),
        ])->save();

        return $delivery->refresh();
    }

    private function statusFor(string $providerStatus): NotificationDeliveryStatus
    {
        return match (strtolower($providerStatus)) {
            'delivered' => NotificationDeliveryStatus::Delivered,
            'failed', 'undeliverable' => NotificationDeliveryStatus::Failed,
            'read' => NotificationDeliveryStatus::Read,
            default => NotificationDeliveryStatus::Sent,
        };
    }

    private function stringValue(mixed $value): ?string
    {
        return is_scalar($value) && $value !== '' ? (string) $value : null;
    }
}
