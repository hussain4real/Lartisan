<?php

use App\Actions\Artisans\CreateArtisanBusinessProfile;
use App\Actions\Artisans\UpdateArtisanBusinessLocation;
use App\Enums\PlatformRole;
use App\Enums\TeamRole;
use App\Models\AdminProfile;
use App\Models\AreaAgentAssignment;
use App\Models\LocalGovernment;
use App\Models\Territory;
use App\Models\User;
use Database\Seeders\GeographySeeder;
use Database\Seeders\PlatformAccessSeeder;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\Facades\File;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\withoutVite;

test('pwa manifest and icons meet installable web app requirements', function (): void {
    $manifestPath = public_path('manifest.webmanifest');
    expect(File::exists($manifestPath))->toBeTrue();

    $decoded = json_decode(File::get($manifestPath), true);

    if (! is_array($decoded)) {
        throw new RuntimeException('manifest.webmanifest did not decode to an array.');
    }

    expect($decoded['id'] ?? null)->toBe('/')
        ->and($decoded['start_url'] ?? null)->toBe('/')
        ->and($decoded['scope'] ?? null)->toBe('/')
        ->and($decoded['display'] ?? null)->toBe('standalone')
        ->and($decoded['theme_color'] ?? null)->toBe('#001C72')
        ->and($decoded['background_color'] ?? null)->toBe('#F8FAFC')
        ->and($decoded['icons'] ?? null)->toBeArray()->toHaveCount(4)
        ->and($decoded['shortcuts'] ?? null)->toBeArray()->toHaveCount(2);

    $shortcuts = $decoded['shortcuts'] ?? null;

    if (! is_array($shortcuts)) {
        throw new RuntimeException('manifest.webmanifest shortcuts must be an array.');
    }

    $shortcutUrls = array_map(
        static fn (mixed $shortcut): ?string => is_array($shortcut) && is_string($shortcut['url'] ?? null) ? $shortcut['url'] : null,
        $shortcuts,
    );
    expect($shortcutUrls)->toContain('/marketplace', '/agent');

    $iconAssertions = [
        'pwa-192x192.png' => [192, 192],
        'pwa-512x512.png' => [512, 512],
        'pwa-maskable-512x512.png' => [512, 512],
    ];

    foreach ($iconAssertions as $filename => [$expectedWidth, $expectedHeight]) {
        $iconPath = public_path($filename);
        expect(File::exists($iconPath))->toBeTrue();

        $size = getimagesize($iconPath);
        expect($size)->toBeArray()
            ->and($size[0] ?? null)->toBe($expectedWidth)
            ->and($size[1] ?? null)->toBe($expectedHeight);
    }
});

test('service worker script configures offline fallback and network-only exclusions', function (): void {
    $serviceWorkerPath = public_path('sw.js');
    expect(File::exists($serviceWorkerPath))->toBeTrue();

    $contents = File::get($serviceWorkerPath);

    expect($contents)
        ->toContain('lartisan-pwa-v1')
        ->toContain("OFFLINE_URL = '/offline'")
        ->toContain('SKIP_WAITING')
        ->toContain('/webhooks/')
        ->toContain('/livewire')
        ->toContain('X-Inertia');
});

test('offline route renders the offline fallback page for guests and authenticated users', function (): void {
    withoutVite();

    get(route('offline'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->component('Offline'));

    $user = User::factory()->create();

    actingAs($user);

    get(route('offline'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->component('Offline'));
});

test('admin host catch-all does not redirect pwa assets to /admin', function (): void {
    get('https://admin.lartisan.app/dashboard')
        ->assertRedirect('/admin');

    get('https://admin.lartisan.app/manifest.webmanifest')
        ->assertNotFound();

    get('https://admin.lartisan.app/sw.js')
        ->assertNotFound();
});

test('filament pwa hooks output manifest metadata, service worker registration, and field draft support', function (): void {
    $headHtml = FilamentView::renderHook(PanelsRenderHook::HEAD_END)->toHtml();
    $bodyHtml = FilamentView::renderHook(PanelsRenderHook::BODY_END)->toHtml();

    expect($headHtml)
        ->toContain('href="/manifest.webmanifest"')
        ->toContain('content="#001C72"');

    expect($bodyHtml)
        ->toContain('id="filament-pwa-banner"')
        ->toContain('id="filament-install-app"')
        ->toContain("navigator.serviceWorker.register('/sw.js')")
        ->toContain('lartisan:offline-draft:filament-field-visit');
});

test('kyc screen exposes field visit permissions, statuses, and territories for assigned area agents only', function (): void {
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
        businessName: 'Phase Seventeen Electrical',
        onboardedByAgent: $agent,
    );

    $profile = app(UpdateArtisanBusinessLocation::class)->handle(
        profile: $profile,
        country: $country,
        state: $state,
        localGovernment: $localGovernment,
        territory: $territory,
    );

    $team = $profile->team()->firstOrFail();
    $team->members()->syncWithoutDetaching([
        $agent->id => ['role' => TeamRole::Member->value],
    ]);

    withoutVite();
    actingAs($owner);

    get(route('artisan.kyc.show', ['current_team' => $team]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('artisan/Kyc')
            ->where('profile.territoryId', $territory->id)
            ->where('canRecordFieldVisit', false)
            ->has('fieldVisitStatuses')
            ->has('territories'));

    $agent->switchTeam($team);

    actingAs($agent);

    get(route('artisan.kyc.show', ['current_team' => $team]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('artisan/Kyc')
            ->where('profile.territoryId', $territory->id)
            ->where('canRecordFieldVisit', true)
            ->has('fieldVisitStatuses')
            ->has('territories'));
});
