<?php

use App\Http\Controllers\Artisan\OnboardingController;
use App\Http\Controllers\Teams\TeamInvitationController;
use App\Http\Middleware\EnsureTeamMembership;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', fn () => Inertia::render('Welcome'))->name('home');

Route::prefix('{current_team}')
    ->middleware(['auth', 'verified', EnsureTeamMembership::class])
    ->group(function () {
        Route::get('dashboard', fn () => Inertia::render('Dashboard'))->name('dashboard');
        Route::get('artisan/onboarding', [OnboardingController::class, 'create'])->name('artisan.onboarding.create');
        Route::post('artisan/onboarding', [OnboardingController::class, 'store'])->name('artisan.onboarding.store');
    });

Route::middleware(['auth'])->group(function () {
    Route::get('invitations/{invitation}/accept', [TeamInvitationController::class, 'accept'])->name('invitations.accept');
});

require __DIR__.'/settings.php';
