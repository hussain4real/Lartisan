<?php

namespace App\Http\Controllers\Customer;

use App\Actions\Customers\CreateCustomerProfile;
use App\Enums\PreferredChannel;
use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\UpdateCustomerPreferencesRequest;
use App\Models\CustomerProfile;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PreferenceController extends Controller
{
    public function show(Request $request): Response
    {
        $user = $this->user($request);
        $profile = $user->customerProfile()->first();
        $preferences = $profile instanceof CustomerProfile ? ($profile->preferences ?? []) : [];

        return Inertia::render('customer/Preferences', [
            'preferences' => [
                'preferredChannel' => is_string($preferences['preferred_channel'] ?? null)
                    ? $preferences['preferred_channel']
                    : ($user->preferred_channel instanceof PreferredChannel ? $user->preferred_channel->value : PreferredChannel::Whatsapp->value),
                'scheduleWindow' => is_string($preferences['schedule_window'] ?? null) ? $preferences['schedule_window'] : 'flexible',
                'defaultNotes' => is_string($preferences['default_notes'] ?? null) ? $preferences['default_notes'] : null,
            ],
            'channels' => collect(PreferredChannel::cases())
                ->map(fn (PreferredChannel $channel): array => [
                    'value' => $channel->value,
                    'label' => str($channel->value)->replace('_', ' ')->title()->toString(),
                ])
                ->values()
                ->all(),
            'scheduleWindows' => [
                ['value' => 'flexible', 'label' => __('Flexible')],
                ['value' => 'morning', 'label' => __('Morning')],
                ['value' => 'afternoon', 'label' => __('Afternoon')],
                ['value' => 'evening', 'label' => __('Evening')],
                ['value' => 'weekend', 'label' => __('Weekend')],
            ],
        ]);
    }

    public function update(
        UpdateCustomerPreferencesRequest $request,
        CreateCustomerProfile $createCustomerProfile,
    ): RedirectResponse {
        $user = $this->user($request);
        $preferences = $request->preferences();

        $createCustomerProfile->handle(
            user: $user,
            defaultAddressId: $user->customerProfile()->first()?->default_address_id,
            preferences: $preferences,
        );
        $user->forceFill(['preferred_channel' => PreferredChannel::from($preferences['preferred_channel'])])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Booking preferences saved.')]);

        return back();
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        assert($user instanceof User);

        return $user;
    }
}
