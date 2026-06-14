<?php

use App\Enums\WaitlistAudienceType;
use App\Models\Country;
use App\Models\LocalGovernment;
use App\Models\ServiceCategory;
use App\Models\State;
use App\Models\Territory;
use App\Models\WaitlistEntry;
use Database\Seeders\GeographySeeder;
use Illuminate\Support\Facades\Artisan;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    config(['lartisan.waitlist_hosts' => ['lartisan.app', 'www.lartisan.app']]);

    $this->withoutVite();
});

test('main domain root redirects to the waitlist', function (): void {
    $this
        ->get('https://lartisan.app/')
        ->assertRedirect('/waitlist');
});

test('main domain app routes redirect to the waitlist', function (string $path): void {
    $this
        ->get("https://lartisan.app{$path}")
        ->assertRedirect('/waitlist');
})->with([
    'marketplace' => '/marketplace',
    'login' => '/login',
    'pricing' => '/pricing',
]);

test('main domain unknown routes redirect to the waitlist', function (): void {
    $this
        ->get('https://lartisan.app/something-that-does-not-exist')
        ->assertRedirect('/waitlist');
});

test('main domain waitlist renders the waitlist page', function (): void {
    $context = createWaitlistContext();

    $this
        ->get('https://lartisan.app/waitlist')
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('Waitlist')
            ->where('audienceTypes.0.value', WaitlistAudienceType::Customer->value)
            ->where('geography.countries.0.name', $context['country']->name)
            ->where('serviceCategories.0.name', $context['category']->name)
            ->where('joined', false)
            ->where('joinedEmail', null));
});

test('waitlist country options include outside Nigeria', function (): void {
    createWaitlistContext();
    createOutsideNigeriaCountry();

    $this
        ->get('https://lartisan.app/waitlist')
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('Waitlist')
            ->where('geography.countries.0.name', 'Primary Nigeria')
            ->where('geography.countries.0.isoCode', 'NG')
            ->where('geography.countries.1.name', 'Outside Nigeria')
            ->where('geography.countries.1.isoCode', Country::OUTSIDE_NIGERIA_ISO_CODE)
            ->where('geography.countries.1.states', []));
});

test('staging domain keeps the full welcome page', function (): void {
    $this
        ->get('https://staging.lartisan.app/')
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page->component('Welcome'));
});

test('valid waitlist submission creates an entry and shows inline success', function (): void {
    $context = createWaitlistContext();

    $this
        ->post('https://lartisan.app/waitlist', waitlistPayload($context))
        ->assertRedirect('/waitlist');

    $this->assertDatabaseHas(WaitlistEntry::class, [
        'name' => 'Amina Bello',
        'email' => 'amina@example.com',
        'phone' => '+234 800 000 0000',
        'audience_type' => WaitlistAudienceType::Artisan->value,
        'business_name' => 'Bright Sparks Electrical',
        'service_category_id' => $context['category']->id,
        'country_id' => $context['country']->id,
        'state_id' => $context['state']->id,
        'local_government_id' => $context['localGovernment']->id,
        'territory_id' => $context['territory']->id,
        'note' => 'We cover residential electrical repairs.',
        'contact_consent' => true,
    ]);

    $this
        ->get('https://lartisan.app/waitlist')
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('Waitlist')
            ->where('joined', true)
            ->where('joinedEmail', 'amina@example.com'));
});

test('outside Nigeria waitlist submission creates an entry without local geography', function (): void {
    $context = createWaitlistContext();
    $outsideNigeria = createOutsideNigeriaCountry();

    $this
        ->post('https://lartisan.app/waitlist', waitlistPayload($context, [
            'country_id' => $outsideNigeria->id,
            'state_id' => null,
            'local_government_id' => null,
            'territory_id' => null,
        ]))
        ->assertRedirect('/waitlist');

    $this->assertDatabaseHas(WaitlistEntry::class, [
        'email' => 'amina@example.com',
        'country_id' => $outsideNigeria->id,
        'state_id' => null,
        'local_government_id' => null,
        'territory_id' => null,
    ]);
});

test('nullable waitlist geography migration backfills rows before rollback', function (): void {
    $this->seed(GeographySeeder::class);

    $outsideNigeria = Country::query()
        ->where('iso_code', Country::OUTSIDE_NIGERIA_ISO_CODE)
        ->firstOrFail();

    $entry = WaitlistEntry::factory()->create([
        'country_id' => $outsideNigeria->id,
        'state_id' => null,
        'local_government_id' => null,
        'territory_id' => null,
    ]);

    try {
        expect(Artisan::call('migrate:rollback', ['--step' => 1, '--no-interaction' => true]))->toBe(0);

        $entry->refresh();
        $country = Country::query()->where('iso_code', 'NG')->firstOrFail();
        $state = State::query()->where('slug', 'federal-capital-territory')->firstOrFail();
        $localGovernment = LocalGovernment::query()->where('slug', 'abuja-municipal-area-council')->firstOrFail();

        expect($entry->country_id)->toBe($country->id)
            ->and($entry->state_id)->toBe($state->id)
            ->and($entry->local_government_id)->toBe($localGovernment->id);
    } finally {
        Artisan::call('migrate', [
            '--path' => 'database/migrations/2026_06_14_082715_allow_waitlist_entries_without_local_geography.php',
            '--no-interaction' => true,
        ]);
    }
});

test('duplicate email submissions update the existing waitlist entry', function (): void {
    $context = createWaitlistContext();

    $this
        ->post('https://lartisan.app/waitlist', waitlistPayload($context, [
            'email' => 'AMINA@example.com',
            'name' => 'Original Name',
            'phone' => '+234 801 111 1111',
            'note' => 'First note.',
        ]))
        ->assertRedirect('/waitlist');

    $this
        ->post('https://lartisan.app/waitlist', waitlistPayload($context, [
            'email' => 'amina@example.com',
            'name' => 'Updated Name',
            'phone' => '+234 802 222 2222',
            'audience_type' => WaitlistAudienceType::Customer->value,
            'business_name' => null,
            'service_category_id' => null,
            'territory_id' => null,
            'note' => 'Updated note.',
        ]))
        ->assertRedirect('/waitlist');

    expect(WaitlistEntry::query()->where('email', 'amina@example.com')->count())->toBe(1);

    $this->assertDatabaseHas(WaitlistEntry::class, [
        'email' => 'amina@example.com',
        'name' => 'Updated Name',
        'phone' => '+234 802 222 2222',
        'audience_type' => WaitlistAudienceType::Customer->value,
        'business_name' => null,
        'service_category_id' => null,
        'territory_id' => null,
        'note' => 'Updated note.',
    ]);
});

test('waitlist entries expose casts and relationships', function (): void {
    $context = createWaitlistContext();

    $entry = WaitlistEntry::factory()->create([
        'audience_type' => WaitlistAudienceType::Operations->value,
        'contact_consent' => 1,
        'service_category_id' => $context['category']->id,
        'country_id' => $context['country']->id,
        'state_id' => $context['state']->id,
        'local_government_id' => $context['localGovernment']->id,
        'territory_id' => $context['territory']->id,
    ]);

    expect($entry->audience_type)->toBe(WaitlistAudienceType::Operations)
        ->and($entry->contact_consent)->toBeTrue()
        ->and($entry->serviceCategory()->is($context['category']))->toBeTrue()
        ->and($entry->country()->is($context['country']))->toBeTrue()
        ->and($entry->state()->is($context['state']))->toBeTrue()
        ->and($entry->localGovernment()->is($context['localGovernment']))->toBeTrue()
        ->and($entry->territory()->is($context['territory']))->toBeTrue();
});

test('waitlist submission validates required fields', function (): void {
    $this
        ->post('https://lartisan.app/waitlist', [])
        ->assertSessionHasErrors([
            'name',
            'email',
            'phone',
            'audience_type',
            'country_id',
            'state_id',
            'local_government_id',
            'contact_consent',
        ]);
});

test('waitlist submission validates geography relationships', function (): void {
    $context = createWaitlistContext();
    $otherContext = createWaitlistContext('Other');

    $this
        ->post('https://lartisan.app/waitlist', waitlistPayload($context, [
            'state_id' => $otherContext['state']->id,
            'local_government_id' => $context['localGovernment']->id,
            'territory_id' => $otherContext['territory']->id,
        ]))
        ->assertSessionHasErrors([
            'state_id',
            'local_government_id',
            'territory_id',
        ]);
});

test('outside Nigeria waitlist submission rejects local geography', function (): void {
    $context = createWaitlistContext();
    $outsideNigeria = createOutsideNigeriaCountry();

    $this
        ->post('https://lartisan.app/waitlist', waitlistPayload($context, [
            'country_id' => $outsideNigeria->id,
        ]))
        ->assertSessionHasErrors([
            'state_id',
            'local_government_id',
            'territory_id',
        ]);
});

/**
 * @return array{country: Country, state: State, localGovernment: LocalGovernment, territory: Territory, category: ServiceCategory}
 */
function createWaitlistContext(string $prefix = 'Primary'): array
{
    $country = Country::factory()->create([
        'name' => "{$prefix} Nigeria",
        'iso_code' => $prefix === 'Primary' ? 'NG' : strtoupper(substr($prefix, 0, 1)).'G',
    ]);
    $state = State::factory()->for($country)->create([
        'name' => "{$prefix} Federal Capital Territory",
        'slug' => strtolower($prefix).'-federal-capital-territory',
    ]);
    $localGovernment = LocalGovernment::factory()->for($state)->create([
        'name' => "{$prefix} Abuja Municipal Area Council",
        'slug' => strtolower($prefix).'-abuja-municipal-area-council',
    ]);
    $territory = Territory::factory()->for($localGovernment)->create([
        'name' => "{$prefix} Wuse",
        'slug' => strtolower($prefix).'-wuse',
    ]);
    $category = ServiceCategory::factory()->create([
        'name' => "{$prefix} Electrical",
        'slug' => strtolower($prefix).'-electrical',
        'sort_order' => 1,
    ]);

    return [
        'country' => $country,
        'state' => $state,
        'localGovernment' => $localGovernment,
        'territory' => $territory,
        'category' => $category,
    ];
}

function createOutsideNigeriaCountry(): Country
{
    return Country::factory()->create([
        'name' => 'Outside Nigeria',
        'iso_code' => Country::OUTSIDE_NIGERIA_ISO_CODE,
        'currency_code' => 'XXX',
        'phone_country_code' => '+000',
    ]);
}

/**
 * @param  array{country: Country, state: State, localGovernment: LocalGovernment, territory: Territory, category: ServiceCategory}  $context
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function waitlistPayload(array $context, array $overrides = []): array
{
    return [
        'name' => 'Amina Bello',
        'email' => 'amina@example.com',
        'phone' => '+234 800 000 0000',
        'audience_type' => WaitlistAudienceType::Artisan->value,
        'business_name' => 'Bright Sparks Electrical',
        'service_category_id' => $context['category']->id,
        'country_id' => $context['country']->id,
        'state_id' => $context['state']->id,
        'local_government_id' => $context['localGovernment']->id,
        'territory_id' => $context['territory']->id,
        'note' => 'We cover residential electrical repairs.',
        'contact_consent' => '1',
        ...$overrides,
    ];
}
