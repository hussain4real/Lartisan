<?php

namespace App\Actions\Reports;

use App\Enums\DisputeStatus;
use App\Enums\PayoutStatus;
use App\Enums\PlatformPermission;
use App\Enums\PlatformRole;
use App\Enums\ReportSnapshotScope;
use App\Enums\ReviewStatus;
use App\Enums\SupportCaseStatus;
use App\Models\AdminProfile;
use App\Models\ArtisanProfile;
use App\Models\Booking;
use App\Models\Dispute;
use App\Models\LocalGovernment;
use App\Models\Payout;
use App\Models\ReportSnapshot;
use App\Models\Review;
use App\Models\State;
use App\Models\SupportCase;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class GenerateScopedReport
{
    public function handle(User $actor, ?CarbonInterface $periodStart = null, ?CarbonInterface $periodEnd = null): ReportSnapshot
    {
        $this->authorize($actor);

        $profileIds = ArtisanProfile::query()
            ->visibleTo($actor)
            ->pluck('id');

        $bookingQuery = Booking::query()->whereIn('artisan_profile_id', $profileIds);
        $this->applyPeriod($bookingQuery, $periodStart, $periodEnd);

        $disputeQuery = Dispute::query()->whereIn('artisan_profile_id', $profileIds);
        $this->applyPeriod($disputeQuery, $periodStart, $periodEnd);

        $payoutQuery = Payout::query()->whereIn('artisan_profile_id', $profileIds);
        $this->applyPeriod($payoutQuery, $periodStart, $periodEnd);

        $reviewQuery = Review::query()->whereIn('artisan_profile_id', $profileIds);
        $this->applyPeriod($reviewQuery, $periodStart, $periodEnd);

        $openDisputeIds = (clone $disputeQuery)
            ->whereNotIn('status', [DisputeStatus::Resolved->value, DisputeStatus::Closed->value])
            ->pluck('id');

        [$scope, $scopeable] = $this->scopeFor($actor);

        return ReportSnapshot::query()->create([
            'generated_by' => $actor->id,
            'scope' => $scope,
            'scopeable_type' => $scopeable?->getMorphClass(),
            'scopeable_id' => $scopeable?->getKey(),
            'period_start' => $periodStart?->toDateString(),
            'period_end' => $periodEnd?->toDateString(),
            'metrics' => [
                'artisan_profiles' => $profileIds->count(),
                'bookings_total' => (clone $bookingQuery)->count(),
                'confirmed_bookings' => (clone $bookingQuery)->whereNotNull('confirmed_at')->count(),
                'gross_booking_value' => (int) (clone $bookingQuery)->whereNotNull('confirmed_at')->sum('quoted_amount'),
                'open_disputes' => $openDisputeIds->count(),
                'published_reviews' => (clone $reviewQuery)->where('status', ReviewStatus::Published->value)->count(),
                'pending_payouts' => (clone $payoutQuery)->whereIn('status', [
                    PayoutStatus::Pending->value,
                    PayoutStatus::InReview->value,
                    PayoutStatus::Approved->value,
                    PayoutStatus::Retrying->value,
                ])->count(),
                'paid_payouts' => (clone $payoutQuery)->where('status', PayoutStatus::Paid->value)->count(),
                'open_support_cases' => SupportCase::query()
                    ->where('supportable_type', (new Dispute)->getMorphClass())
                    ->whereIn('supportable_id', $openDisputeIds)
                    ->whereNotIn('status', [SupportCaseStatus::Resolved->value, SupportCaseStatus::Closed->value])
                    ->count(),
            ],
            'generated_at' => now(),
        ]);
    }

    private function authorize(User $actor): void
    {
        $canReport = collect([
            PlatformPermission::ViewGlobalReports,
            PlatformPermission::ViewStateReports,
            PlatformPermission::ViewLocalGovernmentReports,
            PlatformPermission::ViewAreaReports,
        ])->contains(fn (PlatformPermission $permission): bool => $actor->can($permission->value));

        if (! $canReport) {
            throw new AuthorizationException('You cannot generate scoped reports.');
        }
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     */
    private function applyPeriod(Builder $query, ?CarbonInterface $periodStart, ?CarbonInterface $periodEnd): void
    {
        if ($periodStart instanceof CarbonInterface) {
            $query->where('created_at', '>=', $periodStart->startOfDay());
        }

        if ($periodEnd instanceof CarbonInterface) {
            $query->where('created_at', '<=', $periodEnd->endOfDay());
        }
    }

    /**
     * @return array{0: ReportSnapshotScope, 1: Model|null}
     */
    private function scopeFor(User $actor): array
    {
        if ($actor->can(PlatformPermission::ViewGlobalReports->value)) {
            return [ReportSnapshotScope::Global, null];
        }

        $adminProfile = $actor->adminProfile()->first();

        if (! $adminProfile instanceof AdminProfile) {
            return [ReportSnapshotScope::AreaAgent, $actor];
        }

        if ($adminProfile->role === PlatformRole::StateCoordinator
            && $adminProfile->scope_type === (new State)->getMorphClass()
            && $adminProfile->scope_id !== null) {
            return [ReportSnapshotScope::State, State::query()->find($adminProfile->scope_id)];
        }

        if ($adminProfile->role === PlatformRole::LocalGovernmentAdmin
            && $adminProfile->scope_type === (new LocalGovernment)->getMorphClass()
            && $adminProfile->scope_id !== null) {
            return [ReportSnapshotScope::LocalGovernment, LocalGovernment::query()->find($adminProfile->scope_id)];
        }

        return [ReportSnapshotScope::AreaAgent, $actor];
    }
}
