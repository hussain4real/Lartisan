<?php

namespace App\Actions\Payments;

use App\Enums\ArtisanSubscriptionStatus;
use App\Enums\ArtisanVerificationStatus;
use App\Enums\PaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Models\ArtisanProfile;
use App\Models\Payment;
use App\Models\Subscription;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ActivateSubscription
{
    public function __construct(
        private readonly EnsureWallet $ensureWallet,
    ) {}

    public function handle(Payment $payment): Subscription
    {
        return DB::transaction(function () use ($payment): Subscription {
            $lockedPayment = Payment::query()
                ->whereKey($payment->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedPayment->subscription_id !== null) {
                return $lockedPayment->subscription()->firstOrFail();
            }

            if ($lockedPayment->status !== PaymentStatus::Successful) {
                throw new InvalidArgumentException('Only successful payments can activate a subscription.');
            }

            $profile = ArtisanProfile::query()
                ->whereKey($lockedPayment->artisan_profile_id)
                ->lockForUpdate()
                ->firstOrFail();
            $plan = $lockedPayment->plan()->firstOrFail();
            $startsAt = now();
            $endsAt = $startsAt->copy()->addDays($plan->duration_days);

            Subscription::query()
                ->where('artisan_profile_id', $profile->id)
                ->where('status', SubscriptionStatus::Active)
                ->update([
                    'ends_at' => $startsAt,
                    'grace_ends_at' => $startsAt,
                    'status' => SubscriptionStatus::Expired,
                ]);

            $subscription = Subscription::query()->create([
                'artisan_profile_id' => $profile->id,
                'subscription_plan_id' => $plan->id,
                'payment_id' => $lockedPayment->id,
                'status' => SubscriptionStatus::Active,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'grace_ends_at' => $endsAt->copy()->addDays(7),
            ]);

            $lockedPayment->forceFill(['subscription_id' => $subscription->id])->save();
            $profile->forceFill([
                'is_public' => $profile->verification_status === ArtisanVerificationStatus::Approved,
                'subscription_status' => ArtisanSubscriptionStatus::Active,
            ])->save();
            $this->ensureWallet->handle($profile, $lockedPayment->currency_code);

            return $subscription->refresh();
        }, attempts: 3);
    }
}
