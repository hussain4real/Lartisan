<?php

namespace App\Providers;

use App\Contracts\Documents\DocumentRenderer;
use App\Contracts\Payments\PaymentProvider;
use App\Contracts\Payouts\PayoutProvider;
use App\Services\Documents\LaravelPdfDocumentRenderer;
use App\Services\Payments\PaystackPaymentProvider;
use App\Services\Payouts\PaystackPayoutProvider;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(DocumentRenderer::class, LaravelPdfDocumentRenderer::class);
        $this->app->bind(PaymentProvider::class, PaystackPaymentProvider::class);
        $this->app->bind(PayoutProvider::class, PaystackPayoutProvider::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureRateLimiting();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }

    /**
     * Configure named rate limiters for public and sensitive workflows.
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('waitlist-submissions', fn (Request $request): Limit => Limit::perMinute($this->rateLimit('waitlist_submissions_per_minute'))
            ->by($request->string('email')->lower()->toString() ?: $request->ip()));

        RateLimiter::for('marketplace-bookings', fn (Request $request): Limit => Limit::perMinute($this->rateLimit('marketplace_bookings_per_minute'))
            ->by($request->user()?->id !== null ? 'user:'.$request->user()->id : (string) $request->ip()));

        RateLimiter::for('booking-tracker-actions', fn (Request $request): Limit => Limit::perMinute($this->rateLimit('booking_tracker_actions_per_minute'))
            ->by(is_scalar($request->route('trackerCode')) ? (string) $request->route('trackerCode') : (string) $request->ip()));

        RateLimiter::for('booking-chat', fn (Request $request): Limit => Limit::perMinute($this->rateLimit('booking_chat_messages_per_minute'))
            ->by($request->user()?->id !== null ? 'user:'.$request->user()->id : (string) $request->ip()));

        RateLimiter::for('identity-otp', fn (Request $request): Limit => Limit::perMinute($this->rateLimit('otp_requests_per_minute'))
            ->by($request->user()?->id !== null ? 'user:'.$request->user()->id : (string) $request->ip()));

        RateLimiter::for('account-claims', fn (Request $request): Limit => Limit::perMinute($this->rateLimit('account_claims_per_minute'))
            ->by($request->string('token')->limit(16)->toString() ?: $request->ip()));

        RateLimiter::for('media-uploads', fn (Request $request): Limit => Limit::perMinute($this->rateLimit('media_uploads_per_minute'))
            ->by($request->user()?->id !== null ? 'user:'.$request->user()->id : (string) $request->ip()));

        RateLimiter::for('paystack-webhooks', fn (Request $request): Limit => Limit::perMinute($this->rateLimit('paystack_webhooks_per_minute'))
            ->by((string) $request->ip()));

        RateLimiter::for('whatsapp-webhooks', fn (Request $request): Limit => Limit::perMinute($this->rateLimit('whatsapp_webhooks_per_minute'))
            ->by((string) $request->ip()));
    }

    private function rateLimit(string $key): int
    {
        $value = config("lartisan.rate_limits.{$key}");

        return max(1, is_numeric($value) ? (int) $value : 1);
    }
}
