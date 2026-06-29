<?php

namespace App\Actions\Bookings;

use App\Enums\ArtisanAvailabilityStatus;
use App\Enums\ArtisanServiceStatus;
use App\Enums\ArtisanSubscriptionStatus;
use App\Enums\ArtisanVerificationStatus;
use App\Enums\SubscriptionStatus;
use App\Models\ArtisanProfile;
use App\Models\LocalGovernment;
use App\Models\ServiceCategory;
use App\Models\State;
use App\Models\Territory;
use App\Support\Marketplace\ProximitySearch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Pagination\LengthAwarePaginator;

class SearchArtisans
{
    /**
     * @return Collection<int, ArtisanProfile>
     */
    public function handle(
        ?string $query = null,
        ?ServiceCategory $category = null,
        ?State $state = null,
        ?LocalGovernment $localGovernment = null,
        ?Territory $territory = null,
        ?ProximitySearch $proximity = null,
        int $limit = 12,
    ): Collection {
        return $this->ranked($query, $category, $state, $localGovernment, $territory, $proximity)
            ->take($limit);
    }

    /**
     * @param  array<array-key, mixed>  $queryParameters
     * @return LengthAwarePaginator<int, ArtisanProfile>
     */
    public function paginate(
        ?string $query = null,
        ?ServiceCategory $category = null,
        ?State $state = null,
        ?LocalGovernment $localGovernment = null,
        ?Territory $territory = null,
        ?ProximitySearch $proximity = null,
        int $perPage = 12,
        int $page = 1,
        string $path = '/',
        array $queryParameters = [],
    ): LengthAwarePaginator {
        $page = max(1, $page);
        $perPage = max(1, $perPage);
        $ranked = $this->ranked($query, $category, $state, $localGovernment, $territory, $proximity);

        return new LengthAwarePaginator(
            items: $ranked->forPage($page, $perPage)->values(),
            total: $ranked->count(),
            perPage: $perPage,
            currentPage: $page,
            options: [
                'path' => $path,
                'query' => $queryParameters,
            ],
        );
    }

    /**
     * @return Collection<int, ArtisanProfile>
     */
    private function ranked(
        ?string $query = null,
        ?ServiceCategory $category = null,
        ?State $state = null,
        ?LocalGovernment $localGovernment = null,
        ?Territory $territory = null,
        ?ProximitySearch $proximity = null,
    ): Collection {
        $queryText = $query === null ? null : trim($query);

        $builder = ArtisanProfile::query()
            ->with([
                'country',
                'state',
                'localGovernment',
                'territory',
                'services' => function (Relation $query): void {
                    $query->getQuery()
                        ->with('category')
                        ->where('status', ArtisanServiceStatus::Active)
                        ->orderBy('sort_order');
                },
            ])
            ->where('verification_status', ArtisanVerificationStatus::Approved)
            ->where('subscription_status', ArtisanSubscriptionStatus::Active)
            ->where('availability_status', '!=', ArtisanAvailabilityStatus::Vacation)
            ->where('is_public', true)
            ->whereHas('subscriptions', fn (Builder $query): Builder => $query
                ->where('status', SubscriptionStatus::Active)
                ->where('ends_at', '>', now()))
            ->whereHas('services', function (Builder $query) use ($category): void {
                $query->where('status', ArtisanServiceStatus::Active);

                if ($category instanceof ServiceCategory) {
                    $query->where('service_category_id', $category->id);
                }
            });

        if ($queryText !== null && $queryText !== '') {
            $builder = $this->applyTextSearch($builder, $queryText);
        }

        if ($state instanceof State) {
            $builder->where('state_id', $state->id);
        }

        if ($localGovernment instanceof LocalGovernment) {
            $builder->where('local_government_id', $localGovernment->id);
        }

        if ($territory instanceof Territory) {
            $builder->where('territory_id', $territory->id);
        }

        if ($proximity instanceof ProximitySearch) {
            $builder
                ->whereNotNull('marketplace_latitude')
                ->whereNotNull('marketplace_longitude')
                ->whereBetween('marketplace_latitude', $proximity->latitudeBounds())
                ->whereBetween('marketplace_longitude', $proximity->longitudeBounds());
        }

        $profiles = $builder->orderBy('business_name')->get();

        if ($proximity instanceof ProximitySearch) {
            /** @var Collection<int, ArtisanProfile> $ranked */
            $ranked = $profiles
                ->map(fn (ArtisanProfile $profile): ArtisanProfile => $this->withDistance($profile, $proximity))
                ->filter(fn (ArtisanProfile $profile): bool => $this->isWithinProximity($profile, $proximity))
                ->sort(fn (ArtisanProfile $first, ArtisanProfile $second): int => $this->compareByProximity(
                    $first,
                    $second,
                    $category,
                    $state,
                    $localGovernment,
                    $territory,
                    $proximity,
                ))
                ->values();

            return $ranked;
        }

        /** @var Collection<int, ArtisanProfile> $ranked */
        $ranked = $profiles
            ->sortByDesc(fn (ArtisanProfile $profile): int => $this->score($profile, $category, $state, $localGovernment, $territory, $proximity))
            ->values();

        return $ranked;
    }

    /**
     * @param  Builder<ArtisanProfile>  $builder
     * @return Builder<ArtisanProfile>
     */
    private function applyTextSearch(Builder $builder, string $query): Builder
    {
        return $builder->where(function (Builder $builder) use ($query): void {
            $builder
                ->where('business_name', 'like', '%'.$query.'%')
                ->orWhere('public_summary', 'like', '%'.$query.'%')
                ->orWhereHas('services', function (Builder $serviceQuery) use ($query): void {
                    $serviceQuery
                        ->where('status', ArtisanServiceStatus::Active)
                        ->where(function (Builder $textQuery) use ($query): void {
                            $textQuery
                                ->where('title', 'like', '%'.$query.'%')
                                ->orWhere('description', 'like', '%'.$query.'%');
                        });
                });
        });
    }

    private function score(
        ArtisanProfile $profile,
        ?ServiceCategory $category,
        ?State $state,
        ?LocalGovernment $localGovernment,
        ?Territory $territory,
        ?ProximitySearch $proximity,
    ): int {
        $score = 0;

        if ($category instanceof ServiceCategory && $profile->services->contains('service_category_id', $category->id)) {
            $score += 40;
        }

        if ($territory instanceof Territory && $profile->territory_id === $territory->id) {
            $score += 80;
        }

        if ($localGovernment instanceof LocalGovernment && $profile->local_government_id === $localGovernment->id) {
            $score += 50;
        }

        if ($state instanceof State && $profile->state_id === $state->id) {
            $score += 20;
        }

        $score += match ($profile->availability_status) {
            ArtisanAvailabilityStatus::Online => 15,
            ArtisanAvailabilityStatus::Busy => 5,
            default => 0,
        };

        if ($proximity instanceof ProximitySearch) {
            $distance = $this->distanceFromAttribute($profile);

            if ($distance !== null) {
                $score += max(0, 100 - (int) round(($distance / max(1, $proximity->radiusKm)) * 100));
            }
        }

        return $score;
    }

    private function withDistance(ArtisanProfile $profile, ProximitySearch $proximity): ArtisanProfile
    {
        $distance = $proximity->distanceTo($profile->marketplace_latitude, $profile->marketplace_longitude);

        if ($distance !== null) {
            $profile->setAttribute('distance_km', round($distance, 1));
        }

        return $profile;
    }

    private function isWithinProximity(ArtisanProfile $profile, ProximitySearch $proximity): bool
    {
        $distance = $this->distanceFromAttribute($profile);

        if ($distance === null || $distance > $proximity->radiusKm) {
            return false;
        }

        return $profile->service_radius_km === null || $distance <= $profile->service_radius_km;
    }

    private function compareByProximity(
        ArtisanProfile $first,
        ArtisanProfile $second,
        ?ServiceCategory $category,
        ?State $state,
        ?LocalGovernment $localGovernment,
        ?Territory $territory,
        ProximitySearch $proximity,
    ): int {
        $scoreComparison = $this->score($second, $category, $state, $localGovernment, $territory, $proximity)
            <=> $this->score($first, $category, $state, $localGovernment, $territory, $proximity);

        if ($scoreComparison !== 0) {
            return $scoreComparison;
        }

        $distanceComparison = ($this->distanceFromAttribute($first) ?? PHP_FLOAT_MAX)
            <=> ($this->distanceFromAttribute($second) ?? PHP_FLOAT_MAX);

        if ($distanceComparison !== 0) {
            return $distanceComparison;
        }

        return $first->business_name <=> $second->business_name;
    }

    private function distanceFromAttribute(ArtisanProfile $profile): ?float
    {
        $distance = $profile->getAttribute('distance_km');

        return is_numeric($distance) ? (float) $distance : null;
    }
}
