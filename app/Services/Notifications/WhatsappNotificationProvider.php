<?php

namespace App\Services\Notifications;

use App\Contracts\Notifications\NotificationProvider;
use App\Models\NotificationDelivery;
use App\Support\Notifications\NotificationProviderReceipt;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class WhatsappNotificationProvider implements NotificationProvider
{
    public function enabled(): bool
    {
        return (bool) config('lartisan.notifications.channels.whatsapp.enabled')
            && $this->baseUrl() !== ''
            && $this->token() !== '';
    }

    public function providerName(): string
    {
        return 'whatsapp';
    }

    public function send(NotificationDelivery $delivery): NotificationProviderReceipt
    {
        $response = Http::withToken($this->token())
            ->acceptJson()
            ->asJson()
            ->connectTimeout($this->connectTimeout())
            ->timeout($this->timeout())
            ->retry($this->retryTimes(), $this->retrySleepMilliseconds())
            ->post($this->baseUrl().'/messages', [
                'body' => $delivery->body,
                'dedupe_key' => $delivery->dedupe_key,
                'recipient' => $delivery->recipient_address,
                'subject' => $delivery->subject,
            ]);

        if ($response->failed()) {
            throw new RuntimeException('WhatsApp provider request failed with status '.$response->status().'.');
        }

        $metadata = $this->metadataFromResponse($response->json());

        return new NotificationProviderReceipt(
            messageId: $this->stringFromPayload($metadata, 'message_id') ?? $this->stringFromPayload($metadata, 'id'),
            status: $this->stringFromPayload($metadata, 'status') ?? 'sent',
            metadata: $metadata,
        );
    }

    private function baseUrl(): string
    {
        return rtrim($this->configString('base_url'), '/');
    }

    private function connectTimeout(): int
    {
        return max(1, $this->configInteger('connect_timeout', 3));
    }

    private function retrySleepMilliseconds(): int
    {
        return max(0, $this->configInteger('retry_sleep_milliseconds', 200));
    }

    private function retryTimes(): int
    {
        return max(0, $this->configInteger('retry_times', 2));
    }

    private function timeout(): int
    {
        return max(1, $this->configInteger('timeout', 5));
    }

    private function token(): string
    {
        return $this->configString('token');
    }

    private function configInteger(string $key, int $default): int
    {
        $value = config("lartisan.notifications.channels.whatsapp.{$key}", $default);

        return is_numeric($value) ? (int) $value : $default;
    }

    private function configString(string $key): string
    {
        $value = config("lartisan.notifications.channels.whatsapp.{$key}", '');

        return is_scalar($value) ? (string) $value : '';
    }

    /**
     * @return array<string, mixed>
     */
    private function metadataFromResponse(mixed $payload): array
    {
        $metadata = [];

        if (! is_array($payload)) {
            return $metadata;
        }

        foreach ($payload as $key => $value) {
            if (is_string($key)) {
                $metadata[$key] = $value;
            }
        }

        return $metadata;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function stringFromPayload(array $payload, string $key): ?string
    {
        $value = $payload[$key] ?? null;

        return is_scalar($value) && $value !== '' ? (string) $value : null;
    }
}
