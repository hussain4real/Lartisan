<?php

namespace App\Enums;

enum PlatformPermission: string
{
    case ManagePlatformSettings = 'platform.settings.manage';
    case ManagePlatformRoles = 'platform.roles.manage';

    case ManageStateCoordinators = 'admins.state.manage';
    case ManageLocalGovernmentAdmins = 'admins.lga.manage';
    case ManageAreaAgents = 'admins.area.manage';

    case ManageTerritories = 'territories.manage';
    case AssignTerritories = 'territories.assign';

    case SubmitFieldKyc = 'kyc.field.submit';
    case ReviewStandardKyc = 'kyc.standard.review';
    case ReviewEscalatedKyc = 'kyc.escalated.review';

    case ModerateArtisanProfiles = 'profiles.artisan.moderate';

    case ViewScopedBookings = 'bookings.scoped.view';
    case ManageBookingExceptions = 'bookings.exceptions.manage';

    case ViewPayments = 'finance.payments.view';
    case ManagePayouts = 'finance.payouts.manage';

    case ManageSupportCases = 'support.cases.manage';

    case ViewGlobalReports = 'reports.global.view';
    case ViewStateReports = 'reports.state.view';
    case ViewLocalGovernmentReports = 'reports.lga.view';
    case ViewAreaReports = 'reports.area.view';

    case ManageOwnArtisanProfile = 'artisan.profile.manage';
    case ManageOwnServices = 'artisan.services.manage';
    case ManageOwnSubscription = 'artisan.subscription.manage';
    case ViewOwnWallet = 'artisan.wallet.view';

    case ManageOwnBookings = 'customer.bookings.manage';
    case CreateReviews = 'customer.reviews.create';
    case CreateGuestBookings = 'customer.guest-bookings.create';
}
