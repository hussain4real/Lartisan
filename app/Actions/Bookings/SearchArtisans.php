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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\Relation;

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
        int $limit = 12,
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

        $profiles = $builder->orderBy('business_name')->get();

        /** @var Collection<int, ArtisanProfile> $ranked */
        $ranked = $profiles
            ->sortByDesc(fn (ArtisanProfile $profile): int => $this->score($profile, $category, $state, $localGovernment, $territory))
            ->values()
            ->take($limit);

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

        return $score + match ($profile->availability_status) {
            ArtisanAvailabilityStatus::Online => 15,
            ArtisanAvailabilityStatus::Busy => 5,
            default => 0,
        };
    }
}
