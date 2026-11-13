<?php

use App\Actions\Artisans\CreateArtisanBusinessProfile;
use App\Actions\Artisans\SubmitKyc;
use App\Actions\Artisans\UpdateArtisanBusinessLocation;
use App\Enums\PlatformRole;
use App\Enums\TeamRole;
use App\Models\AdminProfile;
use App\Models\AreaAgentAssignment;
use App\Models\FieldVisit;
use App\Models\LocalGovernment;
use App\Models\Territory;
use App\Models\User;
use Database\Seeders\GeographySeeder;
use Database\Seeders\PlatformAccessSeeder;

test('offline page renders fallback guidance and counts locally saved drafts', function (): void {
    $page = visit(route('offline', absolute: false));

    $page->script(<<<'JS'
        localStorage.setItem(
            'lartisan:offline-draft:test-visit',
            JSON.stringify({
                data: { notes: 'Saved in field' },
                savedAt: new Date().toISOString(),
                queuedForSync: true,
            })
        );
        Object.defineProperty(navigator, 'onLine', { configurable: true, get: () => false });
        window.dispatchEvent(new Event('offline'));
    JS);

    $page->assertSee('You are currently offline')
        ->assertSeeIn('@offline-connection-badge', 'Offline mode')
        ->assertSeeIn('@offline-saved-drafts-count', '1')
        ->assertVisible('@offline-retry-button')
        ->assertNoSmoke();
})->group('smoke');

test('marketplace pwa install banner handles beforeinstallprompt fallback and AbortError', function (): void {
    $page = visit(route('marketplace.index', absolute: false));

    $page->script(<<<'JS'
        const abortedPrompt = new Event('beforeinstallprompt');
        abortedPrompt.prompt = () => Promise.reject(new DOMException('User canceled', 'AbortError'));
        abortedPrompt.userChoice = Promise.resolve({ outcome: 'dismissed', platform: 'web' });
        window.dispatchEvent(abortedPrompt);
    JS);

    $page->assertVisible('@pwa-install-button')
        ->click('@pwa-install-button')
        ->assertSeeIn('@pwa-install-status', 'Installation was canceled or could not complete.')
        ->assertNoSmoke();

    $acceptedPage = visit(route('marketplace.index', absolute: false));

    $acceptedPage->script(<<<'JS'
        const acceptedPrompt = new Event('beforeinstallprompt');
        acceptedPrompt.prompt = () => Promise.resolve();
        acceptedPrompt.userChoice = Promise.resolve({ outcome: 'accepted', platform: 'web' });
        window.dispatchEvent(acceptedPrompt);
    JS);

    $acceptedPage->assertVisible('@pwa-install-button')
        ->click('@pwa-install-button')
        ->assertSeeIn('@pwa-install-status', 'The app was installed.')
        ->assertNoSmoke();
})->group('smoke');

test('area agent can capture field visit gps offline, preserve draft across reload, and submit online', function (): void {
    $this->seed(GeographySeeder::class);
    $this->seed(PlatformAccessSeeder::class);

    $localGovernment = LocalGovernment::query()
        ->where('slug', 'abuja-municipal-area-council')
        ->firstOrFail();
    $territory = Territory::query()
        ->where('slug', 'wuse-market')
        ->firstOrFail();
    $state = $localGovernment->state()->firstOrFail();
    $country = $state->country()->firstOrFail();

    $owner = User::factory()->create();
    $agent = User::factory()->create();
    $agent->assignRole(PlatformRole::AreaAgent->value);

    AdminProfile::factory()->create([
        'user_id' => $agent->id,
        'role' => PlatformRole::AreaAgent,
        'scope_type' => $localGovernment->getMorphClass(),
        'scope_id' => $localGovernment->id,
    ]);

    AreaAgentAssignment::factory()->create([
        'user_id' => $agent->id,
        'territory_id' => $territory->id,
        'assigned_by' => $owner->id,
    ]);

    $profile = app(CreateArtisanBusinessProfile::class)->handle(
        owner: $owner,
        businessName: 'Phase Seventeen Field Artisan',
        onboardedByAgent: $agent,
    );

    $profile = app(UpdateArtisanBusinessLocation::class)->handle(
        profile: $profile,
        country: $country,
        state: $state,
        localGovernment: $localGovernment,
        territory: $territory,
    );

    app(SubmitKyc::class)->handle($profile, $owner);

    $team = $profile->team()->firstOrFail();
    $team->members()->syncWithoutDetaching([
        $agent->id => ['role' => TeamRole::Member->value],
    ]);
    $agent->switchTeam($team);

    $kycUrl = route('artisan.kyc.show', ['current_team' => $team], false);

    $this->actingAs($agent);

    $page = visit($kycUrl)
        ->geolocation(9.076478, 7.468659);

    $note = 'Verified workshop generator and tools during intermittent connectivity.';

    $page->assertVisible('@field-visit-section')
        ->click('@field-visit-capture-gps')
        ->assertSee('GPS coordinates captured.')
        ->assertValue('@field-visit-latitude', '9.076478')
        ->assertValue('@field-visit-longitude', '7.468659')
        ->fill('@field-visit-notes', $note);

    $page->script(<<<'JS'
        Object.defineProperty(navigator, 'onLine', { configurable: true, get: () => false });
        window.dispatchEvent(new Event('offline'));
    JS);

    $page->click('@field-visit-submit')
        ->assertSee('Offline mode: Draft saved on this device and queued to sync when your connection returns.');

    expect(FieldVisit::query()->count())->toBe(0);

    $page->refresh()
        ->assertVisible('@field-visit-section')
        ->assertValue('@field-visit-notes', $note)
        ->assertValue('@field-visit-latitude', '9.076478')
        ->assertValue('@field-visit-longitude', '7.468659')
        ->click('@field-visit-submit')
        ->assertSee('Field visit recorded and local draft cleared.')
        ->assertNoSmoke();

    $visit = FieldVisit::query()->firstOrFail();

    expect($visit->notes)->toBe($note)
        ->and((float) $visit->latitude)->toBe(9.076478)
        ->and((float) $visit->longitude)->toBe(7.468659);
})->group('smoke');
