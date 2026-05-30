<?php

namespace App\Actions\Payments;

use App\Contracts\Payments\PaymentProvider;
use App\Enums\PaymentProviderName;
use App\Enums\PaymentPurpose;
use App\Enums\PaymentStatus;
use App\Models\ArtisanProfile;
use App\Models\Payment;
use App\Models\SubscriptionPlan;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

class InitializePayment
{
    public function __construct(
        private readonly PaymentProvider $paymentProvider,
    ) {}

    public function handle(ArtisanProfile $profile, SubscriptionPlan $plan, string $callbackUrl): Payment
    {
        if (! $plan->active) {
            throw new InvalidArgumentException('The selected subscription plan is not active.');
        }

        $payment = Payment::query()->create([
            'artisan_profile_id' => $profile->id,
            'subscription_plan_id' => $plan->id,
            'provider' => PaymentProviderName::Paystack,
            'purpose' => PaymentPurpose::Subscription,
            'status' => PaymentStatus::Pending,
            'reference' => $this->reference(),
            'amount' => $plan->price_amount,
            'currency_code' => strtoupper($plan->currency_code),
        ]);

        try {
            $initialization = $this->paymentProvider->initialize($payment, $callbackUrl);
        } catch (Throwable $throwable) {
            $payment->forceFill([
                'failed_at' => now(),
                'failure_reason' => $throwable->getMessage(),
                'status' => PaymentStatus::Failed,
            ])->save();

            throw $throwable;
        }

        $payment->forceFill([
            'access_code' => $initialization->accessCode,
            'checkout_url' => $initialization->authorizationUrl,
            'provider_payload' => $initialization->raw,
            'provider_reference' => $initialization->reference,
        ])->save();

        return $payment->refresh();
    }

    private function reference(): string
    {
        do {
            $reference = 'lartisan-'.Str::lower((string) Str::ulid());
        } while (Payment::query()->where('reference', $reference)->exists());

        return $reference;
    }
}
