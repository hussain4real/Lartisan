<?php

namespace App\Actions\Setup;

use App\Actions\Artisans\CreateArtisanBusinessProfile;
use App\Actions\Artisans\UpdateArtisanBusinessLocation;
use App\Enums\ArtisanAvailabilityStatus;
use App\Enums\ArtisanServiceStatus;
use App\Enums\ArtisanSubscriptionStatus;
use App\Enums\ArtisanVerificationStatus;
use App\Enums\PlatformRole;
use App\Enums\PreferredChannel;
use App\Enums\SubscriptionStatus;
use App\Enums\TeamKind;
use App\Enums\TeamRole;
use App\Enums\UserStatus;
use App\Models\ArtisanProfile;
use App\Models\ArtisanService;
use App\Models\Country;
use App\Models\ServiceCategory;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\Team;
use App\Models\Territory;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

class SeedMarketplaceCatalog
{
    private const PASSWORD = 'password';

    private const ARTISAN_COUNT = 20;

    private const OFFERINGS_PER_ARTISAN = 10;

    public function __construct(
        private readonly CreateArtisanBusinessProfile $createArtisanBusinessProfile,
        private readonly UpdateArtisanBusinessLocation $updateArtisanBusinessLocation,
    ) {}

    /**
     * @param  array<string, ServiceCategory>  $categories
     * @return array{profiles: int, services: int}
     */
    public function handle(User $areaAgent, array $categories): array
    {
        $country = Country::query()->where('iso_code', 'NG')->firstOrFail();
        $plan = SubscriptionPlan::query()->where('slug', 'growth-listing')->firstOrFail();
        $territories = $this->territories();
        $businesses = array_slice($this->businesses(), 0, self::ARTISAN_COUNT);
        $categoryKeys = array_keys($categories);
        $templates = $this->offeringTemplates();
        $serviceCount = 0;

        foreach ($businesses as $businessIndex => $business) {
            $territory = $territories[$businessIndex % count($territories)];
            $localGovernment = $territory->localGovernment()->firstOrFail();
            $state = $localGovernment->state()->firstOrFail();
            $owner = $this->upsertOwner($businessIndex + 1, $business['owner']);
            $profile = $this->upsertProfile($owner, $business, $areaAgent);

            $profile = $this->updateArtisanBusinessLocation->handle(
                profile: $profile,
                country: $country,
                state: $state,
                localGovernment: $localGovernment,
                territory: $territory,
            );

            $this->publishProfile($profile, $business);
            $this->upsertSubscription($profile, $plan);

            for ($offeringIndex = 0; $offeringIndex < self::OFFERINGS_PER_ARTISAN; $offeringIndex++) {
                $categoryKey = $categoryKeys[($businessIndex + $offeringIndex) % count($categoryKeys)];
                $categoryTemplates = $templates[$categoryKey];
                $template = $categoryTemplates[($businessIndex + $offeringIndex) % count($categoryTemplates)];

                $this->upsertOffering(
                    profile: $profile,
                    category: $categories[$categoryKey],
                    template: $template,
                    sortOrder: $offeringIndex + 1,
                );

                $serviceCount++;
            }
        }

        return [
            'profiles' => count($businesses),
            'services' => $serviceCount,
        ];
    }

    /**
     * @return array<int, Territory>
     */
    private function territories(): array
    {
        $slugs = [
            'wuse-market',
            'garki-market',
            'gwarinpa-estate',
            'jabi-community',
            'bwari-central-ward',
            'kubwa-trade-cluster',
            'gwagwalada-market',
            'zuba-community',
            'kuje-market',
            'kwali-market',
            'computer-village-cluster',
            'alausa-secretariat',
            'yaba-market',
            'sabon-gari-market',
            'kofar-wambai-cluster',
            'farm-centre-market',
            'mile-one-market',
            'trans-amadi-cluster',
            'kawo-market',
            'narayi-community',
        ];

        $territories = Territory::query()
            ->with('localGovernment.state')
            ->where('active', true)
            ->whereIn('slug', $slugs)
            ->get()
            ->keyBy('slug');

        $ordered = collect($slugs)
            ->map(fn (string $slug): ?Territory => $territories->get($slug))
            ->filter()
            ->values()
            ->all();

        if (count($ordered) < self::ARTISAN_COUNT) {
            throw new RuntimeException('The marketplace catalog seed requires at least 20 active territories.');
        }

        return $ordered;
    }

    /**
     * @param  array{owner: string, business_name: string, summary: string, years_experience: int, radius: int}  $business
     */
    private function upsertProfile(User $owner, array $business, User $areaAgent): ArtisanProfile
    {
        $profile = $owner->artisanProfiles()
            ->where('business_name', $business['business_name'])
            ->first();

        if ($profile instanceof ArtisanProfile) {
            $profile->forceFill([
                'onboarded_by_agent_id' => $areaAgent->id,
                'internal_notes' => 'Marketplace catalog seed profile.',
            ])->save();

            return $profile->refresh();
        }

        return $this->createArtisanBusinessProfile->handle(
            owner: $owner,
            businessName: $business['business_name'],
            onboardedByAgent: $areaAgent,
            internalNotes: 'Marketplace catalog seed profile.',
        );
    }

    /**
     * @param  array{owner: string, business_name: string, summary: string, years_experience: int, radius: int}  $business
     */
    private function publishProfile(ArtisanProfile $profile, array $business): void
    {
        $phoneNumber = str_pad((string) (8070000000 + $profile->id), 10, '0', STR_PAD_LEFT);

        $profile->forceFill([
            'public_summary' => $business['summary'],
            'years_experience' => $business['years_experience'],
            'service_radius_km' => $business['radius'],
            'public_phone' => '+234'.$phoneNumber,
            'public_email' => Str::slug($business['business_name']).'@lartisan.test',
            'verification_status' => ArtisanVerificationStatus::Approved,
            'subscription_status' => ArtisanSubscriptionStatus::Active,
            'availability_status' => $profile->id % 3 === 0
                ? ArtisanAvailabilityStatus::Busy
                : ArtisanAvailabilityStatus::Online,
            'approved_at' => now(),
            'is_public' => true,
        ])->save();
    }

    private function upsertSubscription(ArtisanProfile $profile, SubscriptionPlan $plan): Subscription
    {
        return Subscription::query()->updateOrCreate(
            [
                'artisan_profile_id' => $profile->id,
                'payment_id' => null,
            ],
            [
                'subscription_plan_id' => $plan->id,
                'status' => SubscriptionStatus::Active,
                'starts_at' => now()->subDay(),
                'ends_at' => now()->addYear(),
                'grace_ends_at' => now()->addYear()->addDays(7),
            ],
        );
    }

    /**
     * @param  array{title: string, description: string, price: string}  $template
     */
    private function upsertOffering(ArtisanProfile $profile, ServiceCategory $category, array $template, int $sortOrder): ArtisanService
    {
        return ArtisanService::query()->updateOrCreate(
            [
                'artisan_profile_id' => $profile->id,
                'title' => $template['title'],
            ],
            [
                'service_category_id' => $category->id,
                'description' => $template['description'],
                'starting_price' => $template['price'],
                'currency_code' => 'NGN',
                'status' => ArtisanServiceStatus::Active,
                'sort_order' => $sortOrder,
            ],
        );
    }

    private function upsertOwner(int $index, string $name): User
    {
        $phoneNumber = (string) (8070000000 + $index);
        $phoneE164 = '+234'.$phoneNumber;
        $email = 'catalog.artisan.'.str_pad((string) $index, 3, '0', STR_PAD_LEFT).'@lartisan.test';
        $user = User::query()->where('email', $email)->first();

        if (! $user instanceof User) {
            $user = new User;
            $user->forceFill([
                'name' => $name,
                'email' => $email,
                'email_verified_at' => now(),
                'phone_country_code' => '+234',
                'phone_number' => $phoneNumber,
                'phone_e164' => $phoneE164,
                'phone_verified_at' => now(),
                'password' => Hash::make(self::PASSWORD),
                'preferred_channel' => PreferredChannel::Whatsapp,
                'remember_token' => Str::random(10),
                'status' => UserStatus::Active,
            ]);
            $user->save();
        } else {
            $user->forceFill([
                'name' => $name,
                'email_verified_at' => now(),
                'phone_country_code' => '+234',
                'phone_number' => $phoneNumber,
                'phone_e164' => $phoneE164,
                'phone_verified_at' => $user->phone_verified_at ?? now(),
                'preferred_channel' => PreferredChannel::Whatsapp,
                'status' => UserStatus::Active,
            ])->save();
        }

        $this->ensurePersonalTeam($user);
        $user->assignRole(PlatformRole::Artisan->value);

        return $user->refresh();
    }

    private function ensurePersonalTeam(User $user): void
    {
        if ($user->personalTeam() instanceof Team) {
            return;
        }

        $team = Team::query()->create([
            'name' => $user->name."'s Team",
            'kind' => TeamKind::Personal,
            'is_personal' => true,
        ]);

        $team->members()->syncWithoutDetaching([
            $user->id => ['role' => TeamRole::Owner->value],
        ]);

        if ($user->current_team_id === null) {
            $user->switchTeam($team);
        }
    }

    /**
     * @return array<int, array{owner: string, business_name: string, summary: string, years_experience: int, radius: int}>
     */
    private function businesses(): array
    {
        return [
            ['owner' => 'Amina Bello', 'business_name' => 'Amina Home Works', 'summary' => 'Home repair and product supply team for busy households.', 'years_experience' => 8, 'radius' => 18],
            ['owner' => 'Chinedu Okafor', 'business_name' => 'Oakline Fixers', 'summary' => 'Residential maintenance crew covering repairs, fittings, and finishing.', 'years_experience' => 12, 'radius' => 25],
            ['owner' => 'Fatima Musa', 'business_name' => 'Northern Craft Studio', 'summary' => 'Tailored household goods, fashion pieces, and light repairs.', 'years_experience' => 10, 'radius' => 20],
            ['owner' => 'Tunde Adeyemi', 'business_name' => 'Tunde Technical Services', 'summary' => 'Electrical, appliance, and electronics support for homes and shops.', 'years_experience' => 14, 'radius' => 30],
            ['owner' => 'Grace Johnson', 'business_name' => 'Graceful Spaces', 'summary' => 'Cleaning, decor, and home refresh services with product bundles.', 'years_experience' => 7, 'radius' => 16],
            ['owner' => 'Usman Abdullahi', 'business_name' => 'Usman Metal & Auto', 'summary' => 'Metal fabrication, repair, and vehicle support for local traders.', 'years_experience' => 15, 'radius' => 28],
            ['owner' => 'Ifeoma Nwankwo', 'business_name' => 'Ifeoma Tailors Market', 'summary' => 'Everyday tailoring, leather accessories, and made-to-order products.', 'years_experience' => 11, 'radius' => 22],
            ['owner' => 'Sani Ibrahim', 'business_name' => 'Sani Water Systems', 'summary' => 'Plumbing repairs, pump servicing, and water-system product supply.', 'years_experience' => 9, 'radius' => 24],
            ['owner' => 'Bola Martins', 'business_name' => 'Bola Beauty Bench', 'summary' => 'Beauty, grooming, and event-ready household services.', 'years_experience' => 6, 'radius' => 15],
            ['owner' => 'Ebere Uche', 'business_name' => 'Ebere Food & Events', 'summary' => 'Small-event catering, packed products, and market-ready food services.', 'years_experience' => 13, 'radius' => 26],
            ['owner' => 'Khalid Yusuf', 'business_name' => 'Khalid Phone Clinic', 'summary' => 'Phone repairs, accessories, and electronics support.', 'years_experience' => 8, 'radius' => 18],
            ['owner' => 'Maryam Garba', 'business_name' => 'Maryam Paint House', 'summary' => 'Painting, wall finishing, and decorative product bundles.', 'years_experience' => 10, 'radius' => 21],
            ['owner' => 'Samuel Eze', 'business_name' => 'Samuel Woodcraft', 'summary' => 'Custom woodwork, furniture repairs, and practical home products.', 'years_experience' => 16, 'radius' => 30],
            ['owner' => 'Hadiza Aliyu', 'business_name' => 'Hadiza Home Care', 'summary' => 'Cleaning, garment care, and household setup services.', 'years_experience' => 5, 'radius' => 14],
            ['owner' => 'Emeka Obi', 'business_name' => 'Emeka Auto Electrics', 'summary' => 'Vehicle electrical work, diagnostics, and spare-part sourcing.', 'years_experience' => 12, 'radius' => 25],
            ['owner' => 'Rashida Sulaiman', 'business_name' => 'Rashida Leather Works', 'summary' => 'Leather repair, bags, belts, and market accessories.', 'years_experience' => 9, 'radius' => 19],
            ['owner' => 'Kunle Balogun', 'business_name' => 'Kunle Pipe & Pump', 'summary' => 'Pipe repairs, pump installs, and plumbing supplies.', 'years_experience' => 11, 'radius' => 23],
            ['owner' => 'Ngozi Okeke', 'business_name' => 'Ngozi Event Kitchen', 'summary' => 'Food trays, baked products, and event support.', 'years_experience' => 8, 'radius' => 20],
            ['owner' => 'Musa Danladi', 'business_name' => 'Musa Fabrication Yard', 'summary' => 'Metal gates, shelves, repairs, and fabrication products.', 'years_experience' => 18, 'radius' => 35],
            ['owner' => 'Lola Adebayo', 'business_name' => 'Lola Style & Groom', 'summary' => 'Styling, grooming, tailoring, and ready-made accessory products.', 'years_experience' => 7, 'radius' => 17],
        ];
    }

    /**
     * @return array<string, array<int, array{title: string, description: string, price: string}>>
     */
    private function offeringTemplates(): array
    {
        return [
            'electrical' => [
                ['title' => 'Residential wiring safety check', 'description' => 'Service: circuit inspection, fault tracing, and minor rewiring.', 'price' => '15000.00'],
                ['title' => 'LED security light kit', 'description' => 'Product: outdoor LED light supply with fitting support.', 'price' => '28000.00'],
                ['title' => 'Inverter changeover installation', 'description' => 'Service: inverter and changeover wiring for homes and small shops.', 'price' => '65000.00'],
            ],
            'plumbing' => [
                ['title' => 'Emergency leak repair', 'description' => 'Service: leak isolation, pipe patching, and pressure checks.', 'price' => '12000.00'],
                ['title' => 'Bathroom fitting refresh pack', 'description' => 'Product: taps, shower heads, hoses, and installation support.', 'price' => '42000.00'],
                ['title' => 'Water pump servicing', 'description' => 'Service: pump diagnostics, cleaning, and part replacement guidance.', 'price' => '25000.00'],
            ],
            'carpentry' => [
                ['title' => 'Custom kitchen shelf build', 'description' => 'Service: measurement, fabrication, and fitting for kitchen storage.', 'price' => '85000.00'],
                ['title' => 'Hardwood stool set', 'description' => 'Product: locally finished hardwood stools for homes and shops.', 'price' => '36000.00'],
                ['title' => 'Door and lock refit', 'description' => 'Service: door alignment, hinge repair, and lock installation.', 'price' => '30000.00'],
            ],
            'fashion-tailoring' => [
                ['title' => 'Express native wear tailoring', 'description' => 'Service: measurement, sewing, and finishing for native outfits.', 'price' => '22000.00'],
                ['title' => 'Ankara tote bag set', 'description' => 'Product: reinforced Ankara tote bags for errands and gifting.', 'price' => '18000.00'],
                ['title' => 'Uniform alteration bundle', 'description' => 'Service: school or staff uniform resizing and repairs.', 'price' => '9000.00'],
            ],
            'beauty-grooming' => [
                ['title' => 'Home-service haircut and trim', 'description' => 'Service: mobile haircut, beard trim, and cleanup.', 'price' => '7000.00'],
                ['title' => 'Braided wig care kit', 'description' => 'Product: wig care bundle with detangler and storage pack.', 'price' => '16000.00'],
                ['title' => 'Event makeup session', 'description' => 'Service: makeup and touch-up for small events.', 'price' => '30000.00'],
            ],
            'food-catering' => [
                ['title' => 'Small chops party tray', 'description' => 'Product: assorted small chops tray for office or home events.', 'price' => '35000.00'],
                ['title' => 'Weekly soup bowl prep', 'description' => 'Service: batch cooking and packaging for household meal plans.', 'price' => '45000.00'],
                ['title' => 'Rice and stew catering pan', 'description' => 'Product: ready-to-serve food pan for small gatherings.', 'price' => '55000.00'],
            ],
            'metalwork' => [
                ['title' => 'Security door fabrication', 'description' => 'Service: measurement, welding, and fitting for metal security doors.', 'price' => '180000.00'],
                ['title' => 'Steel shelf unit', 'description' => 'Product: welded steel storage shelf for shops and homes.', 'price' => '95000.00'],
                ['title' => 'Gate hinge repair', 'description' => 'Service: hinge replacement, alignment, and welding touch-up.', 'price' => '40000.00'],
            ],
            'auto-repairs' => [
                ['title' => 'Vehicle diagnostic scan', 'description' => 'Service: basic scan, fault explanation, and repair estimate.', 'price' => '15000.00'],
                ['title' => 'Brake pad supply and fit', 'description' => 'Product: brake pad replacement bundle with labour.', 'price' => '45000.00'],
                ['title' => 'Battery terminal service', 'description' => 'Service: terminal cleaning, cable checks, and replacement guidance.', 'price' => '10000.00'],
            ],
            'phone-electronics' => [
                ['title' => 'Phone screen replacement', 'description' => 'Service: screen replacement and touch-response testing.', 'price' => '38000.00'],
                ['title' => 'Charging accessory pack', 'description' => 'Product: charger, cable, and protective plug bundle.', 'price' => '12000.00'],
                ['title' => 'Laptop software tune-up', 'description' => 'Service: cleanup, update, backup, and basic malware scan.', 'price' => '18000.00'],
            ],
            'cleaning' => [
                ['title' => 'Post-renovation deep clean', 'description' => 'Service: dust removal, surface scrub, and window wipe-down.', 'price' => '60000.00'],
                ['title' => 'Home cleaning starter pack', 'description' => 'Product: mop, brush, gloves, and cleaning liquid bundle.', 'price' => '14500.00'],
                ['title' => 'Sofa and rug wash', 'description' => 'Service: fabric wash, stain care, and drying guidance.', 'price' => '35000.00'],
            ],
            'painting-decor' => [
                ['title' => 'Room repaint package', 'description' => 'Service: wall prep, two-coat repaint, and cleanup.', 'price' => '75000.00'],
                ['title' => 'Textured wall finish sample board', 'description' => 'Product: decorative finish sample board for color selection.', 'price' => '10000.00'],
                ['title' => 'Wallpaper accent wall fitting', 'description' => 'Service: surface prep and wallpaper installation.', 'price' => '50000.00'],
            ],
            'leatherwork' => [
                ['title' => 'Leather sandal repair', 'description' => 'Service: sole repair, stitching, and polish.', 'price' => '8000.00'],
                ['title' => 'Handmade leather belt', 'description' => 'Product: custom-sized leather belt with metal buckle.', 'price' => '14000.00'],
                ['title' => 'Bag zipper replacement', 'description' => 'Service: zipper replacement and seam reinforcement.', 'price' => '9000.00'],
            ],
        ];
    }
}
