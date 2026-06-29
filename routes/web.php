<?php

use App\Http\Controllers\AdminHostRedirectController;
use App\Http\Controllers\Artisan\BookingChatController as ArtisanBookingChatController;
use App\Http\Controllers\Artisan\BookingController as ArtisanBookingController;
use App\Http\Controllers\Artisan\DashboardController as ArtisanDashboardController;
use App\Http\Controllers\Artisan\DisputeController as ArtisanDisputeController;
use App\Http\Controllers\Artisan\FieldVisitController;
use App\Http\Controllers\Artisan\KycController;
use App\Http\Controllers\Artisan\OnboardingController;
use App\Http\Controllers\Artisan\PayoutController as ArtisanPayoutController;
use App\Http\Controllers\Artisan\ProfileController as ArtisanProfileController;
use App\Http\Controllers\Artisan\ReviewResponseController as ArtisanReviewResponseController;
use App\Http\Controllers\Artisan\ServiceController as ArtisanServiceController;
use App\Http\Controllers\Artisan\SubscriptionController as ArtisanSubscriptionController;
use App\Http\Controllers\Artisan\WalletController as ArtisanWalletController;
use App\Http\Controllers\BookingPaymentController;
use App\Http\Controllers\BookingTrackerAccountController;
use App\Http\Controllers\BookingTrackerController;
use App\Http\Controllers\BookingTrackerDisputeController;
use App\Http\Controllers\BookingTrackerReviewController;
use App\Http\Controllers\Customer\BookingChatController as CustomerBookingChatController;
use App\Http\Controllers\Customer\BookingController as CustomerBookingController;
use App\Http\Controllers\Customer\DisputeController as CustomerDisputeController;
use App\Http\Controllers\Customer\FavoriteController as CustomerFavoriteController;
use App\Http\Controllers\Customer\PreferenceController as CustomerPreferenceController;
use App\Http\Controllers\Customer\ReviewController as CustomerReviewController;
use App\Http\Controllers\Identity\AccountClaimController;
use App\Http\Controllers\Identity\PhoneVerificationController;
use App\Http\Controllers\Marketplace\BookingOtpController;
use App\Http\Controllers\MarketplaceController;
use App\Http\Controllers\PricingController;
use App\Http\Controllers\Teams\TeamInvitationController;
use App\Http\Controllers\WaitlistController;
use App\Http\Controllers\WaitlistHostRedirectController;
use App\Http\Controllers\Webhooks\PaystackWebhookController;
use App\Http\Controllers\Webhooks\WhatsappWebhookController;
use App\Http\Middleware\EnsureTeamMembership;
use App\Http\Middleware\NormalizeMarketplaceProximityQuery;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

$adminHost = config('lartisan.admin_host');

if ((! is_string($adminHost) || $adminHost === '') && app()->environment('testing')) {
    $adminHost = 'admin.lartisan.app';
}

if (is_string($adminHost) && $adminHost !== '') {
    Route::domain($adminHost)->group(function (): void {
        Route::any('/', AdminHostRedirectController::class);
        Route::any('{path}', AdminHostRedirectController::class)
            ->where('path', '^(?!(admin|state|lga|agent|livewire(?:-[^/]+)?|filament|build|js|css|fonts|images|storage|cdn-cgi|favicon\\.ico|favicon\\.svg|apple-touch-icon\\.png|robots\\.txt)(/|$)).*$');
    });
}

$waitlistHosts = [];

foreach (config()->array('lartisan.waitlist_hosts') as $waitlistHost) {
    if (! is_string($waitlistHost) || $waitlistHost === '') {
        continue;
    }

    $waitlistHosts[] = trim($waitlistHost);
}

foreach ($waitlistHosts as $waitlistHost) {
    Route::domain($waitlistHost)->group(function (): void {
        Route::any('/', WaitlistHostRedirectController::class);
        Route::any('{path}', WaitlistHostRedirectController::class)
            ->where('path', '^(?!waitlist$).*$');
    });
}

Route::get('/', fn () => Inertia::render('Welcome'))->name('home');
Route::get('waitlist', [WaitlistController::class, 'show'])->name('waitlist.show');
Route::post('waitlist', [WaitlistController::class, 'store'])
    ->middleware('throttle:waitlist-submissions')
    ->name('waitlist.store');
Route::get('pricing', [PricingController::class, 'index'])->name('pricing');
Route::get('privacy-policy', fn () => Inertia::render('Legal/PrivacyPolicy'))->name('privacy-policy');
Route::get('terms-of-service', fn () => Inertia::render('Legal/TermsOfService'))->name('terms-of-service');

Route::post('webhooks/paystack', PaystackWebhookController::class)
    ->middleware('throttle:paystack-webhooks')
    ->name('webhooks.paystack');
Route::post('webhooks/whatsapp', WhatsappWebhookController::class)
    ->middleware('throttle:whatsapp-webhooks')
    ->name('webhooks.whatsapp');

Route::get('marketplace', [MarketplaceController::class, 'index'])
    ->middleware(NormalizeMarketplaceProximityQuery::class)
    ->name('marketplace.index');
Route::post('marketplace/booking-otp', BookingOtpController::class)
    ->middleware('throttle:identity-otp')
    ->name('marketplace.booking-otp.issue');
Route::get('marketplace/artisans/{artisanProfile}', [MarketplaceController::class, 'show'])->name('marketplace.artisans.show');
Route::get('marketplace/artisans/{artisanProfile}/book', [MarketplaceController::class, 'create'])->name('marketplace.bookings.create');
Route::post('marketplace/artisans/{artisanProfile}/bookings', [MarketplaceController::class, 'store'])
    ->middleware('throttle:marketplace-bookings')
    ->name('marketplace.bookings.store');
Route::get('booking-tracker/{trackerCode}', [BookingTrackerController::class, 'show'])->name('booking-tracker.show');
Route::post('booking-tracker/{trackerCode}/payments', [BookingPaymentController::class, 'tracker'])
    ->middleware('throttle:booking-tracker-actions')
    ->name('booking-tracker.payments.store');
Route::post('booking-tracker/{trackerCode}/confirm', [BookingTrackerController::class, 'confirm'])
    ->middleware('throttle:booking-tracker-actions')
    ->name('booking-tracker.confirm');
Route::post('booking-tracker/{trackerCode}/account', BookingTrackerAccountController::class)
    ->middleware('throttle:booking-tracker-actions')
    ->name('booking-tracker.account.store');
Route::post('booking-tracker/{trackerCode}/reviews', BookingTrackerReviewController::class)
    ->middleware('throttle:booking-tracker-actions')
    ->name('booking-tracker.reviews.store');
Route::post('booking-tracker/{trackerCode}/disputes', BookingTrackerDisputeController::class)
    ->middleware('throttle:booking-tracker-actions')
    ->name('booking-tracker.disputes.store');

Route::prefix('{current_team}')
    ->middleware(['auth', 'verified', EnsureTeamMembership::class])
    ->group(function () {
        Route::get('dashboard', fn () => Inertia::render('Dashboard'))->name('dashboard');

        Route::prefix('artisan')->name('artisan.')->group(function () {
            Route::get('/', ArtisanDashboardController::class)->name('dashboard');
            Route::get('onboarding', [OnboardingController::class, 'create'])->name('onboarding.create');
            Route::post('onboarding', [OnboardingController::class, 'store'])->name('onboarding.store');
            Route::get('profile', [ArtisanProfileController::class, 'edit'])->name('profile.edit');
            Route::patch('profile', [ArtisanProfileController::class, 'update'])->name('profile.update');
            Route::post('profile/portfolio', [ArtisanProfileController::class, 'portfolio'])
                ->middleware('throttle:media-uploads')
                ->name('profile.portfolio.store');
            Route::get('services', [ArtisanServiceController::class, 'index'])->name('services.index');
            Route::post('services', [ArtisanServiceController::class, 'store'])->name('services.store');
            Route::get('kyc', [KycController::class, 'show'])->name('kyc.show');
            Route::post('kyc', [KycController::class, 'store'])
                ->middleware('throttle:media-uploads')
                ->name('kyc.store');
            Route::post('field-visits', [FieldVisitController::class, 'store'])
                ->middleware('throttle:media-uploads')
                ->name('field-visits.store');
            Route::get('subscription', [ArtisanSubscriptionController::class, 'show'])->name('subscription.show');
            Route::post('subscription', [ArtisanSubscriptionController::class, 'store'])->name('subscription.store');
            Route::get('wallet', [ArtisanWalletController::class, 'show'])->name('wallet.show');
            Route::post('wallet/payouts', [ArtisanPayoutController::class, 'store'])->name('wallet.payouts.store');
            Route::get('bookings', [ArtisanBookingController::class, 'index'])->name('bookings.index');
            Route::post('bookings/{booking}/accept', [ArtisanBookingController::class, 'accept'])->name('bookings.accept');
            Route::post('bookings/{booking}/reject', [ArtisanBookingController::class, 'reject'])->name('bookings.reject');
            Route::post('bookings/{booking}/start', [ArtisanBookingController::class, 'start'])->name('bookings.start');
            Route::post('bookings/{booking}/finish', [ArtisanBookingController::class, 'finish'])->name('bookings.finish');
            Route::get('bookings/{booking}/chat', [ArtisanBookingChatController::class, 'show'])->name('bookings.chat.show');
            Route::post('bookings/{booking}/chat/messages', [ArtisanBookingChatController::class, 'store'])
                ->middleware('throttle:booking-chat')
                ->name('bookings.chat.messages.store');
            Route::get('bookings/{booking}/disputes/create', [ArtisanDisputeController::class, 'create'])->name('bookings.disputes.create');
            Route::post('bookings/{booking}/disputes', [ArtisanDisputeController::class, 'store'])->name('bookings.disputes.store');
            Route::post('reviews/{review}/response', ArtisanReviewResponseController::class)->name('reviews.response.store');
        });
    });

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('invitations/{invitation}/accept', [TeamInvitationController::class, 'accept'])->name('invitations.accept');

    Route::get('customer/bookings', [CustomerBookingController::class, 'index'])->name('customer.bookings.index');
    Route::get('customer/bookings/{booking}', [CustomerBookingController::class, 'show'])->name('customer.bookings.show');
    Route::post('customer/bookings/{booking}/payments', [BookingPaymentController::class, 'customer'])->name('customer.bookings.payments.store');
    Route::post('customer/bookings/{booking}/confirm', [CustomerBookingController::class, 'confirm'])->name('customer.bookings.confirm');
    Route::get('customer/bookings/{booking}/chat', [CustomerBookingChatController::class, 'show'])->name('customer.bookings.chat.show');
    Route::post('customer/bookings/{booking}/chat/messages', [CustomerBookingChatController::class, 'store'])
        ->middleware('throttle:booking-chat')
        ->name('customer.bookings.chat.messages.store');
    Route::post('customer/bookings/{booking}/reviews', [CustomerReviewController::class, 'store'])->name('customer.bookings.reviews.store');
    Route::get('customer/bookings/{booking}/disputes/create', [CustomerDisputeController::class, 'create'])->name('customer.bookings.disputes.create');
    Route::post('customer/bookings/{booking}/disputes', [CustomerDisputeController::class, 'store'])->name('customer.bookings.disputes.store');
    Route::post('customer/favorites/{artisanProfile}', [CustomerFavoriteController::class, 'store'])->name('customer.favorites.store');
    Route::delete('customer/favorites/{artisanProfile}', [CustomerFavoriteController::class, 'destroy'])->name('customer.favorites.destroy');
    Route::get('customer/preferences', [CustomerPreferenceController::class, 'show'])->name('customer.preferences.show');
    Route::patch('customer/preferences', [CustomerPreferenceController::class, 'update'])->name('customer.preferences.update');

    Route::prefix('identity')->name('identity.')->group(function () {
        Route::get('phone', [PhoneVerificationController::class, 'edit'])->name('phone.edit');
        Route::post('otp', [PhoneVerificationController::class, 'issue'])
            ->middleware('throttle:identity-otp')
            ->name('otp.issue');
        Route::post('otp/verify', [PhoneVerificationController::class, 'verify'])
            ->middleware('throttle:identity-otp')
            ->name('otp.verify');
    });
});

Route::get('claim-account', [AccountClaimController::class, 'show'])->name('account-claim.show');
Route::post('claim-account', [AccountClaimController::class, 'store'])
    ->middleware('throttle:account-claims')
    ->name('account-claim.store');

require __DIR__.'/settings.php';
