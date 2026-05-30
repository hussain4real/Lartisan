<?php

use App\Actions\Artisans\CreateArtisanBusinessWorkspace;
use App\Actions\Customers\CreateCustomerProfile;
use App\Actions\Identity\ClaimAgentCreatedAccount;
use App\Actions\Identity\IssueOtp;
use App\Actions\Identity\VerifyOtp;
use App\Actions\Setup\SeedGeography;
use App\Enums\AccountClaimStatus;
use App\Enums\OtpPurpose;
use App\Enums\PlatformRole;
use App\Enums\PreferredChannel;
use App\Enums\TeamKind;
use App\Enums\UserStatus;
use App\Http\Controllers\Identity\PhoneVerificationController;
use App\Http\Requests\Identity\IssueOtpRequest;
use App\Http\Requests\Identity\VerifyOtpRequest;
use App\Models\AccountClaim;
use App\Models\Address;
use App\Models\Country;
use App\Models\CustomerProfile;
use App\Models\LocalGovernment;
use App\Models\OtpRecord;
use App\Models\State;
use App\Models\Territory;
use App\Models\User;
use App\Support\PhoneNumberNormalizer;
use Database\Seeders\PlatformAccessSeeder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function () {
    $this->withoutVite();
});

/**
 * @return array{country: Country, state: State, localGovernment: LocalGovernment, territory: Territory}
 */
function phaseTwoGeography(): array
{
    app(SeedGeography::class)->handle();

    return [
        'country' => Country::query()->where('iso_code', 'NG')->firstOrFail(),
        'state' => State::query()->where('slug', 'federal-capital-territory')->firstOrFail(),
        'localGovernment' => LocalGovernment::query()->where('slug', 'abuja-municipal-area-council')->firstOrFail(),
        'territory' => Territory::query()->where('slug', 'wuse-market')->firstOrFail(),
    ];
}

test('phase two models expose address otp account claim and customer links', function () {
    $geography = phaseTwoGeography();
    $user = User::factory()->create([
        'phone_country_code' => '+234',
        'phone_number' => '8031234567',
        'phone_e164' => '+2348031234567',
        'phone_verified_at' => now(),
        'preferred_channel' => PreferredChannel::Whatsapp,
    ]);

    $address = Address::factory()->default()->create([
        'user_id' => $user->id,
        'country_id' => $geography['country']->id,
        'state_id' => $geography['state']->id,
        'local_government_id' => $geography['localGovernment']->id,
        'territory_id' => $geography['territory']->id,
    ]);
    $profile = app(CreateCustomerProfile::class)->handle($user, $address->id);
    $otp = OtpRecord::factory()->create([
        'user_id' => $user->id,
        'phone_e164' => '+2348031234567',
        'purpose' => OtpPurpose::PhoneVerification,
    ]);
    $claim = AccountClaim::factory()->create([
        'user_id' => $user->id,
        'claimed_by' => $user->id,
    ]);

    expect($user->status)->toBe(UserStatus::Active);
    expect($user->preferred_channel)->toBe(PreferredChannel::Whatsapp);
    expect($user->addresses()->firstOrFail()->is($address))->toBeTrue();
    expect($user->otpRecords()->firstOrFail()->is($otp))->toBeTrue();
    expect($user->accountClaims()->firstOrFail()->is($claim))->toBeTrue();
    expect($user->claimedAccountClaims()->firstOrFail()->is($claim))->toBeTrue();
    expect($profile->defaultAddress()->firstOrFail()->is($address))->toBeTrue();
    expect($address->user()->firstOrFail()->is($user))->toBeTrue();
    expect($address->country()->firstOrFail()->is($geography['country']))->toBeTrue();
    expect($address->state()->firstOrFail()->is($geography['state']))->toBeTrue();
    expect($address->localGovernment()->firstOrFail()->is($geography['localGovernment']))->toBeTrue();
    expect($address->territory()->firstOrFail()->is($geography['territory']))->toBeTrue();
    expect($geography['country']->addresses()->firstOrFail()->is($address))->toBeTrue();
    expect($geography['state']->addresses()->firstOrFail()->is($address))->toBeTrue();
    expect($geography['localGovernment']->addresses()->firstOrFail()->is($address))->toBeTrue();
    expect($geography['territory']->addresses()->firstOrFail()->is($address))->toBeTrue();
    expect(Address::query()->ownedBy($user)->firstOrFail()->is($address))->toBeTrue();
    expect($otp->user()->firstOrFail()->is($user))->toBeTrue();
    expect(OtpRecord::query()->forPhonePurpose('+2348031234567', OtpPurpose::PhoneVerification)->firstOrFail()->is($otp))->toBeTrue();
    expect($otp->isExpired())->toBeFalse();
    expect($otp->hasAttemptsRemaining())->toBeTrue();
    expect($claim->user()->firstOrFail()->is($user))->toBeTrue();
    expect($claim->claimedBy()->firstOrFail()->is($user))->toBeTrue();
    expect(AccountClaim::query()->pending()->firstOrFail()->is($claim))->toBeTrue();
    expect($claim->isExpired())->toBeFalse();
    expect(CustomerProfile::query()->where('default_address_id', $address->id)->exists())->toBeTrue();
});

test('phone verification page exposes inertia contract', function () {
    $user = User::factory()->create([
        'phone_country_code' => '+234',
        'phone_number' => '8031234567',
        'phone_verified_at' => now(),
        'preferred_channel' => PreferredChannel::Sms,
    ]);

    $this
        ->actingAs($user)
        ->get(route('identity.phone.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('identity/PhoneVerification')
            ->where('phone.countryCode', '+234')
            ->where('phone.number', '8031234567')
            ->where('phone.verified', true)
            ->where('phone.preferredChannel', 'sms')
            ->has('channels', 4));
});

test('otp action issues hashed records and enforces request rate limits', function () {
    $user = User::factory()->create();
    $issueOtp = app(IssueOtp::class);
    $rateLimitKey = $issueOtp->rateLimitKey('+2348031234567', OtpPurpose::PhoneVerification);

    RateLimiter::clear($rateLimitKey);

    $issued = $issueOtp->handle(
        user: $user,
        phoneCountryCode: '+234',
        phoneNumber: '08031234567',
        purpose: OtpPurpose::PhoneVerification,
        plainCode: '654321',
    );

    expect($issued->plainCode)->toBe('654321');
    expect(Hash::check('654321', $issued->record->code_hash))->toBeTrue();
    expect($issued->record->phone_country_code)->toBe('+234');
    expect($issued->record->phone_number)->toBe('8031234567');
    expect($issued->record->phone_e164)->toBe('+2348031234567');
    expect($issued->record->purpose)->toBe(OtpPurpose::PhoneVerification);

    foreach (range(1, 4) as $attempt) {
        $issueOtp->handle($user, '+234', '8031234567', plainCode: (string) (111110 + $attempt));
    }

    expect(fn () => $issueOtp->handle($user, '+234', '8031234567', plainCode: '111116'))
        ->toThrow(ValidationException::class);
});

test('phone verification routes issue and verify otp records', function () {
    $user = User::factory()->create(['preferred_channel' => PreferredChannel::Whatsapp]);

    $this
        ->actingAs($user)
        ->post(route('identity.otp.issue'), [
            'phone_country_code' => '+234',
            'phone_number' => '08031234567',
            'preferred_channel' => 'email',
        ])
        ->assertRedirect();

    expect(OtpRecord::query()->where('phone_e164', '+2348031234567')->exists())->toBeTrue();
    expect(User::query()->findOrFail($user->id)->preferred_channel)->toBe(PreferredChannel::Email);

    OtpRecord::factory()->create([
        'user_id' => $user->id,
        'phone_country_code' => '+234',
        'phone_number' => '8031234567',
        'phone_e164' => '+2348031234567',
        'purpose' => OtpPurpose::PhoneVerification,
        'code_hash' => Hash::make('112233'),
    ]);

    $this
        ->actingAs($user)
        ->post(route('identity.otp.verify'), [
            'phone_country_code' => '+234',
            'phone_number' => '08031234567',
            'code' => '112233',
            'preferred_channel' => 'whatsapp',
        ])
        ->assertRedirect();

    $freshUser = User::query()->findOrFail($user->id);

    expect($freshUser->phone_e164)->toBe('+2348031234567');
    expect($freshUser->phone_verified_at)->not->toBeNull();
    expect($freshUser->preferred_channel)->toBe(PreferredChannel::Whatsapp);
    expect($freshUser->status)->toBe(UserStatus::Active);
});

test('verify otp rejects invalid expired exhausted and wrong codes', function () {
    $verifyOtp = app(VerifyOtp::class);

    expect(fn () => $verifyOtp->handle('+234', '8030000001', '123456'))
        ->toThrow(ValidationException::class);

    $expired = OtpRecord::factory()->expired()->create([
        'phone_e164' => '+2348030000002',
        'phone_number' => '8030000002',
    ]);

    expect(fn () => $verifyOtp->handle('+234', '8030000002', '123456'))
        ->toThrow(ValidationException::class);
    expect($expired->refresh()->consumed_at)->not->toBeNull();

    OtpRecord::factory()->create([
        'phone_e164' => '+2348030000003',
        'phone_number' => '8030000003',
        'attempts' => 5,
        'max_attempts' => 5,
    ]);

    expect(fn () => $verifyOtp->handle('+234', '8030000003', '123456'))
        ->toThrow(ValidationException::class);

    $wrongCode = OtpRecord::factory()->create([
        'phone_e164' => '+2348030000004',
        'phone_number' => '8030000004',
        'code_hash' => Hash::make('123456'),
    ]);

    expect(fn () => $verifyOtp->handle('+234', '8030000004', '654321'))
        ->toThrow(ValidationException::class);
    expect($wrongCode->refresh()->attempts)->toBe(1);
});

test('verify otp preserves suspended user status after phone confirmation', function () {
    $user = User::factory()->create(['status' => UserStatus::Suspended]);

    OtpRecord::factory()->create([
        'user_id' => $user->id,
        'phone_country_code' => '+234',
        'phone_number' => '8030000005',
        'phone_e164' => '+2348030000005',
        'purpose' => OtpPurpose::PhoneVerification,
        'code_hash' => Hash::make('445566'),
    ]);

    app(VerifyOtp::class)->handle('+234', '8030000005', '445566', user: $user);

    expect($user->refresh()->status)->toBe(UserStatus::Suspended);
});

test('phone number normalizer handles prefixed local numbers and rejects invalid input', function () {
    $normalizer = app(PhoneNumberNormalizer::class);

    expect($normalizer->normalize('+234', '+234 0803 123 4567'))->toBe([
        'country_code' => '+234',
        'national' => '8031234567',
        'e164' => '+2348031234567',
    ]);

    expect(fn () => $normalizer->normalize('', '8031234567'))->toThrow(InvalidArgumentException::class);
    expect(fn () => $normalizer->normalize('+234', ''))->toThrow(InvalidArgumentException::class);
    expect(fn () => $normalizer->normalize('+234', '0000'))->toThrow(InvalidArgumentException::class);
});

test('account claim form and action activate an agent created account', function () {
    $user = User::factory()->pendingClaim()->create([
        'name' => 'Agent Captured Artisan',
    ]);
    $token = 'claim-token-123';

    $claim = AccountClaim::factory()->create([
        'user_id' => $user->id,
        'token_hash' => hash('sha256', $token),
    ]);

    $this
        ->get(route('account-claim.show', ['token' => $token]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('auth/ClaimAccount')
            ->where('token', $token));

    $this
        ->post(route('account-claim.store'), [
            'token' => $token,
            'name' => ' Claimed Artisan ',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])
        ->assertRedirect(route('dashboard', ['current_team' => $user->currentTeam]));

    $freshUser = User::query()->findOrFail($user->id);
    $freshClaim = $claim->refresh();

    expect(auth()->id())->toBe($freshUser->id);
    expect($freshUser->name)->toBe('Claimed Artisan');
    expect($freshUser->status)->toBe(UserStatus::Active);
    expect(Hash::check('password', $freshUser->password))->toBeTrue();
    expect($freshClaim->status)->toBe(AccountClaimStatus::Claimed);
    expect($freshClaim->claimed_by)->toBe($freshUser->id);
});

test('account claim controller falls back home when claimed user has no team', function () {
    $user = User::query()->create([
        'name' => 'No Team Claimer',
        'email' => 'no-team-claimer@example.com',
        'password' => Hash::make('password'),
        'status' => UserStatus::PendingClaim,
    ]);
    $token = 'no-team-token';

    AccountClaim::factory()->create([
        'user_id' => $user->id,
        'token_hash' => hash('sha256', $token),
    ]);

    $this
        ->post(route('account-claim.store'), [
            'token' => $token,
            'password' => 'password',
            'password_confirmation' => 'password',
        ])
        ->assertRedirect(route('home'));
});

test('account claim action rejects invalid used and expired tokens', function () {
    $claimAccount = app(ClaimAgentCreatedAccount::class);

    expect(fn () => $claimAccount->handle('missing-token', 'password'))
        ->toThrow(ValidationException::class);

    $claimedToken = 'claimed-token';
    AccountClaim::factory()->claimed()->create([
        'token_hash' => hash('sha256', $claimedToken),
    ]);

    expect(fn () => $claimAccount->handle($claimedToken, 'password'))
        ->toThrow(ValidationException::class);

    $expiredToken = 'expired-token';
    $expiredClaim = AccountClaim::factory()->expired()->create([
        'token_hash' => hash('sha256', $expiredToken),
    ]);

    expect(fn () => $claimAccount->handle($expiredToken, 'password'))
        ->toThrow(ValidationException::class);
    expect($expiredClaim->refresh()->status)->toBe(AccountClaimStatus::Expired);
});

test('create artisan business workspace matches phase two action contract', function () {
    $this->seed(PlatformAccessSeeder::class);
    $geography = phaseTwoGeography();
    $owner = User::factory()->create();
    $agent = User::factory()->create();

    $profile = app(CreateArtisanBusinessWorkspace::class)->handle(
        owner: $owner,
        businessName: 'Phase Two Sparks',
        country: $geography['country'],
        state: $geography['state'],
        localGovernment: $geography['localGovernment'],
        territory: $geography['territory'],
        onboardedByAgent: $agent,
        internalNotes: 'Agent assisted phase two setup.',
    );
    $team = $profile->team()->firstOrFail();
    $freshOwner = User::query()->findOrFail($owner->id);

    expect($team->kind)->toBe(TeamKind::ArtisanBusiness);
    expect($team->is_personal)->toBeFalse();
    expect($profile->user()->firstOrFail()->is($owner))->toBeTrue();
    expect($profile->country()->firstOrFail()->is($geography['country']))->toBeTrue();
    expect($profile->state()->firstOrFail()->is($geography['state']))->toBeTrue();
    expect($profile->localGovernment()->firstOrFail()->is($geography['localGovernment']))->toBeTrue();
    expect($profile->territory()->firstOrFail()->is($geography['territory']))->toBeTrue();
    expect($profile->onboardedByAgent()->firstOrFail()->is($agent))->toBeTrue();
    expect($profile->internal_notes)->toBe('Agent assisted phase two setup.');
    expect($freshOwner->hasRole(PlatformRole::Artisan->value))->toBeTrue();
    expect($freshOwner->current_team_id)->toBe($team->id);
});

test('phone verification controller guards direct calls without a user', function () {
    $controller = app(PhoneVerificationController::class);

    expect(fn () => $controller->edit(Request::create('/identity/phone', 'GET')))
        ->toThrow(HttpException::class);

    expect(fn () => $controller->issue(
        IssueOtpRequest::create('/identity/otp', 'POST'),
        app(IssueOtp::class),
    ))->toThrow(HttpException::class);

    expect(fn () => $controller->verify(
        VerifyOtpRequest::create('/identity/otp/verify', 'POST'),
        app(VerifyOtp::class),
    ))->toThrow(HttpException::class);
});
