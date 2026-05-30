<?php

namespace App\Http\Controllers\Artisan;

use App\Actions\Payments\InitializePayment;
use App\Enums\SubscriptionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Artisan\InitializeSubscriptionPaymentRequest;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class SubscriptionController extends Controller
{
    use ResolvesCurrentArtisanProfile;

    public function show(Request $request): Response
    {
        $profile = $this->artisanProfileFrom($request);

        Gate::authorize('manageSubscription', $profile);

        return Inertia::render('artisan/Subscription', [
            'profile' => [
                'id' => $profile->id,
                'businessName' => $profile->business_name,
                'subscriptionStatus' => $profile->subscription_status->value,
                'verificationStatus' => $profile->verification_status->value,
            ],
            'plans' => SubscriptionPlan::query()
                ->where('active', true)
                ->orderBy('sort_order')
                ->get()
                ->map(fn (SubscriptionPlan $plan): array => $this->planPayload($plan))
                ->all(),
            'currentSubscription' => $this->subscriptionPayload(
                $profile->subscriptions()
                    ->with(['payment', 'plan'])
                    ->where('status', SubscriptionStatus::Active)
                    ->latest('ends_at')
                    ->first(),
            ),
            'recentPayments' => $profile->payments()
                ->with('plan')
                ->latest('id')
                ->limit(6)
                ->get()
                ->map(fn (Payment $payment): array => $this->paymentPayload($payment))
                ->all(),
        ]);
    }

    public function store(
        InitializeSubscriptionPaymentRequest $request,
        InitializePayment $initializePayment,
    ): SymfonyResponse {
        $profile = $this->artisanProfileFrom($request);

        Gate::authorize('manageSubscription', $profile);

        $team = $profile->team()->firstOrFail();
        $payment = $initializePayment->handle(
            profile: $profile,
            plan: $request->subscriptionPlan(),
            callbackUrl: route('artisan.subscription.show', ['current_team' => $team->slug]),
        );

        assert($payment->checkout_url !== null);

        return Inertia::location($payment->checkout_url);
    }

    /**
     * @return array{id: int, name: string, slug: string, description: string|null, priceAmount: int, price: string, currencyCode: string, interval: string, durationDays: int, features: array<int, string>}
     */
    private function planPayload(SubscriptionPlan $plan): array
    {
        /** @var array<int, string> $features */
        $features = $plan->feature_summary ?? [];

        return [
            'id' => $plan->id,
            'name' => $plan->name,
            'slug' => $plan->slug,
            'description' => $plan->description,
            'priceAmount' => $plan->price_amount,
            'price' => number_format($plan->price_amount / 100, 2),
            'currencyCode' => $plan->currency_code,
            'interval' => $plan->interval->value,
            'durationDays' => $plan->duration_days,
            'features' => $features,
        ];
    }

    /**
     * @return array{id: int, status: string, startsAt: string|null, endsAt: string|null, graceEndsAt: string|null, plan: array{id: int, name: string, slug: string, description: string|null, priceAmount: int, price: string, currencyCode: string, interval: string, durationDays: int, features: array<int, string>}, paymentReference: string|null}|null
     */
    private function subscriptionPayload(?Subscription $subscription): ?array
    {
        if (! $subscription instanceof Subscription) {
            return null;
        }

        $plan = $subscription->plan()->firstOrFail();
        $payment = $subscription->payment()->first();

        return [
            'id' => $subscription->id,
            'status' => $subscription->status->value,
            'startsAt' => $subscription->starts_at?->toISOString(),
            'endsAt' => $subscription->ends_at?->toISOString(),
            'graceEndsAt' => $subscription->grace_ends_at?->toISOString(),
            'plan' => $this->planPayload($plan),
            'paymentReference' => $payment?->reference,
        ];
    }

    /**
     * @return array{id: int, status: string, reference: string, amount: int, amountDisplay: string, currencyCode: string, checkoutUrl: string|null, paidAt: string|null, failedAt: string|null, failureReason: string|null, planName: string|null}
     */
    private function paymentPayload(Payment $payment): array
    {
        $plan = $payment->plan()->first();

        return [
            'id' => $payment->id,
            'status' => $payment->status->value,
            'reference' => $payment->reference,
            'amount' => $payment->amount,
            'amountDisplay' => number_format($payment->amount / 100, 2),
            'currencyCode' => $payment->currency_code,
            'checkoutUrl' => $payment->checkout_url,
            'paidAt' => $payment->paid_at?->toISOString(),
            'failedAt' => $payment->failed_at?->toISOString(),
            'failureReason' => $payment->failure_reason,
            'planName' => $plan?->name,
        ];
    }
}
