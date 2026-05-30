<?php

namespace App\Actions\Payments;

use App\Contracts\Payments\PaymentProvider;
use App\Enums\PaymentProviderName;
use App\Enums\PaymentStatus;
use App\Enums\ProviderWebhookEventStatus;
use App\Models\Payment;
use App\Models\ProviderWebhookEvent;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ProcessPaystackWebhook
{
    public function __construct(
        private readonly PaymentProvider $paymentProvider,
        private readonly ActivateSubscription $activateSubscription,
    ) {}

    public function handle(string $payload, ?string $signature): ?Payment
    {
        if (! $this->paymentProvider->webhookSignatureIsValid($payload, $signature)) {
            throw new HttpException(401, 'Invalid Paystack webhook signature.');
        }

        try {
            /** @var mixed $decoded */
            $decoded = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new BadRequestHttpException('Invalid Paystack webhook payload.');
        }

        if (! is_array($decoded)) {
            throw new BadRequestHttpException('Invalid Paystack webhook payload.');
        }

        /** @var array<string, mixed> $eventPayload */
        $eventPayload = $decoded;

        return DB::transaction(function () use ($eventPayload, $signature): ?Payment {
            $data = $this->dataFrom($eventPayload);
            $eventName = $this->stringValue($eventPayload['event'] ?? null) ?? 'unknown';
            $reference = $this->stringValue($data['reference'] ?? null);
            $providerEventId = $this->stringValue($data['id'] ?? null);
            $event = $this->webhookEvent($eventName, $providerEventId, $reference, $eventPayload, $signature);

            if (in_array($event->status, [
                ProviderWebhookEventStatus::Ignored,
                ProviderWebhookEventStatus::Processed,
            ], true)) {
                return $reference === null ? null : Payment::query()->where('reference', $reference)->first();
            }

            if ($reference === null) {
                $this->finishEvent($event, ProviderWebhookEventStatus::Failed, 'Webhook payload did not include a payment reference.');

                return null;
            }

            $payment = Payment::query()
                ->where('reference', $reference)
                ->lockForUpdate()
                ->first();

            if (! $payment instanceof Payment) {
                $this->finishEvent($event, ProviderWebhookEventStatus::Ignored, 'No local payment matched the webhook reference.');

                return null;
            }

            $transactionStatus = $this->stringValue($data['status'] ?? null);

            if ($eventName !== 'charge.success' || $transactionStatus !== 'success') {
                $this->recordFailedPayment($payment, $data);
                $this->finishEvent($event, ProviderWebhookEventStatus::Processed);

                return $payment->refresh();
            }

            if (! $this->amountMatches($payment, $data)) {
                $this->finishEvent($event, ProviderWebhookEventStatus::Failed, 'Webhook amount or currency did not match the payment.');

                return $payment->refresh();
            }

            if ($payment->status !== PaymentStatus::Successful) {
                $payment->forceFill([
                    'paid_at' => now(),
                    'provider_payload' => $eventPayload,
                    'provider_reference' => $reference,
                    'status' => PaymentStatus::Successful,
                ])->save();

                $this->activateSubscription->handle($payment->refresh());
            }

            $this->finishEvent($event, ProviderWebhookEventStatus::Processed);

            return $payment->refresh();
        }, attempts: 3);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function dataFrom(array $payload): array
    {
        /** @var mixed $data */
        $data = $payload['data'] ?? [];

        if (! is_array($data)) {
            return [];
        }

        /** @var array<string, mixed> $data */
        return $data;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function webhookEvent(
        string $eventName,
        ?string $providerEventId,
        ?string $reference,
        array $payload,
        ?string $signature,
    ): ProviderWebhookEvent {
        $eventQuery = ProviderWebhookEvent::query()
            ->where('provider', PaymentProviderName::Paystack)
            ->where('event', $eventName);

        if ($providerEventId !== null) {
            $event = (clone $eventQuery)
                ->where('provider_event_id', $providerEventId)
                ->lockForUpdate()
                ->first();

            if ($event instanceof ProviderWebhookEvent) {
                return $event;
            }
        }

        if ($reference !== null) {
            $event = (clone $eventQuery)
                ->where('reference', $reference)
                ->lockForUpdate()
                ->first();

            if ($event instanceof ProviderWebhookEvent) {
                return $event;
            }
        }

        return ProviderWebhookEvent::query()->create([
            'provider' => PaymentProviderName::Paystack,
            'event' => $eventName,
            'provider_event_id' => $providerEventId,
            'reference' => $reference,
            'payload' => $payload,
            'signature' => $signature,
            'received_at' => now(),
            'status' => ProviderWebhookEventStatus::Pending,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function recordFailedPayment(Payment $payment, array $data): void
    {
        $status = $this->stringValue($data['status'] ?? null);

        if (! in_array($status, ['abandoned', 'failed'], true)) {
            return;
        }

        $payment->forceFill([
            'failed_at' => now(),
            'failure_reason' => $this->stringValue($data['gateway_response'] ?? null) ?? 'Payment was not successful.',
            'provider_payload' => $data,
            'status' => $status === 'abandoned' ? PaymentStatus::Abandoned : PaymentStatus::Failed,
        ])->save();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function amountMatches(Payment $payment, array $data): bool
    {
        return $this->integerValue($data['amount'] ?? null) === $payment->amount
            && strtoupper($this->stringValue($data['currency'] ?? null) ?? '') === $payment->currency_code;
    }

    private function finishEvent(
        ProviderWebhookEvent $event,
        ProviderWebhookEventStatus $status,
        ?string $failureReason = null,
    ): void {
        $event->forceFill([
            'failure_reason' => $failureReason,
            'processed_at' => now(),
            'status' => $status,
        ])->save();
    }

    private function stringValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        return null;
    }

    private function integerValue(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && is_numeric($value)) {
            return (int) $value;
        }

        return null;
    }
}
