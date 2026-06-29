export type TerritoryOption = {
    id: number;
    name: string;
    slug: string;
    type: string;
};

export type LocalGovernmentOption = {
    id: number;
    name: string;
    slug: string;
    territories: TerritoryOption[];
};

export type StateOption = {
    id: number;
    name: string;
    slug: string;
    localGovernments: LocalGovernmentOption[];
};

export type CountryOption = {
    id: number;
    name: string;
    isoCode: string;
    states: StateOption[];
};

export type ArtisanProfileSummary = {
    id: number;
    businessName: string;
    team: {
        name: string;
        slug: string;
    };
    verificationStatus: string;
    subscriptionStatus: string;
    availabilityStatus: string;
    location: string;
};

export type ArtisanDashboardProfile = {
    id: number;
    businessName: string;
    verificationStatus: string;
    availabilityStatus: string;
    isPublic: boolean;
};

export type ArtisanDashboardMetrics = {
    services: number;
    kycSubmissions: number;
    fieldVisits: number;
};

export type ArtisanDashboardService = {
    id: number;
    title: string;
    category: string;
    status: string;
};

export type ArtisanKycSummary = {
    id: number;
    status: string;
    submittedAt: string | null;
};

export type ArtisanPortfolioMedia = {
    id: number;
    name: string;
    fileName: string;
    url: string;
};

export type ArtisanProfileDetail = ArtisanDashboardProfile & {
    publicSummary: string | null;
    yearsExperience: number | null;
    serviceRadiusKm: number | null;
    publicPhone: string | null;
    publicEmail: string | null;
    subscriptionStatus: string;
    portfolio: ArtisanPortfolioMedia[];
};

export type ServiceCategoryOption = {
    id: number;
    name: string;
};

export type ArtisanServiceItem = {
    id: number;
    title: string;
    description: string | null;
    startingPrice: string | null;
    currencyCode: string;
    status: string;
    category: ServiceCategoryOption;
};

export type KycMediaItem = {
    id: number;
    name: string;
    fileName: string;
    url: string;
};

export type KycSubmissionDetail = ArtisanKycSummary & {
    notes: string | null;
    media: Record<string, KycMediaItem | null>;
};

export type SubscriptionPlanOption = {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    priceAmount: number;
    price: string;
    currencyCode: string;
    interval: string;
    durationDays: number;
    features: string[];
};

export type ArtisanSubscriptionDetail = {
    id: number;
    status: string;
    startsAt: string | null;
    endsAt: string | null;
    graceEndsAt: string | null;
    plan: SubscriptionPlanOption;
    paymentReference: string | null;
};

export type ArtisanPaymentItem = {
    id: number;
    status: string;
    reference: string;
    amount: number;
    amountDisplay: string;
    currencyCode: string;
    checkoutUrl: string | null;
    paidAt: string | null;
    failedAt: string | null;
    failureReason: string | null;
    planName: string | null;
};

export type ArtisanWalletSummary = {
    id: number | null;
    currencyCode: string;
    availableBalance: number;
    pendingBalance: number;
    availableDisplay: string;
    pendingDisplay: string;
};

export type WalletLedgerEntryItem = {
    id: number;
    type: string;
    direction: string;
    amount: number;
    amountDisplay: string;
    availableBalanceAfter: number;
    pendingBalanceAfter: number;
    immutableReference: string;
    description: string | null;
    postedAt: string | null;
};

export type PayoutAccountItem = {
    id: number;
    provider: string;
    bankName: string;
    accountName: string;
    status: string;
    verifiedAt: string | null;
};

export type PayoutItem = {
    id: number;
    status: string;
    providerStatus: string | null;
    amount: number;
    amountDisplay: string;
    currencyCode: string;
    requestedAt: string | null;
    approvedAt: string | null;
    processingAt: string | null;
    paidAt: string | null;
    failedAt: string | null;
    reconciledAt: string | null;
    nextRetryAt: string | null;
    trackingReference: string | null;
    failureReason: string | null;
};

export type MarketplaceTerritoryOption = {
    id: number;
    name: string;
};

export type MarketplaceLocalGovernmentOption = {
    id: number;
    name: string;
    territories: MarketplaceTerritoryOption[];
};

export type MarketplaceStateOption = {
    id: number;
    name: string;
    localGovernments: MarketplaceLocalGovernmentOption[];
};

export type MarketplaceFilters = {
    query: string | null;
    serviceCategoryId: number | null;
    stateId: number | null;
    localGovernmentId: number | null;
    territoryId: number | null;
    nearLat: number | null;
    nearLng: number | null;
    radiusKm: number | null;
};

export type MarketplaceArtisanCard = {
    id: number;
    businessName: string;
    availabilityStatus: string;
    verificationStatus: string;
    subscriptionStatus: string;
    location: string;
    distanceKm: number | null;
    distanceLabel: string | null;
    servicesCount: number;
};

export type MarketplaceArtisanPaginator = {
    data: MarketplaceArtisanCard[];
    current_page: number;
    from: number | null;
    last_page: number;
    next_page_url: string | null;
    per_page: number;
    prev_page_url: string | null;
    to: number | null;
    total: number;
};

export type MarketplaceService = {
    id: number;
    title: string;
    description: string | null;
    startingPrice: string | null;
    currencyCode: string;
    category: ServiceCategoryOption;
};

export type MarketplacePortfolioItem = {
    id: number;
    name: string;
    url: string;
};

export type MarketplaceArtisanDetail = MarketplaceArtisanCard & {
    publicSummary: string | null;
    yearsExperience: number | null;
    serviceRadiusKm: number | null;
    publicPhone: string | null;
    publicEmail: string | null;
    isFavorite: boolean;
    services: MarketplaceService[];
    portfolio: MarketplacePortfolioItem[];
};

export type CustomerSavedAddressOption = {
    id: number;
    label: string;
    contactName: string | null;
    phone: string | null;
    line1: string;
    line2: string | null;
    landmark: string | null;
    countryId: number | null;
    stateId: number;
    localGovernmentId: number;
    territoryId: number | null;
    isDefault: boolean;
};

export type CustomerBookingDefaults = {
    name: string | null;
    email: string | null;
    phoneCountryCode: string;
    phoneNumber: string | null;
    preferredChannel: string;
    defaultNotes: string | null;
    scheduleWindow: string;
};

export type BookingServiceSummary = {
    id: number;
    title: string;
    category: string;
};

export type BookingArtisanSummary = {
    id: number;
    businessName: string;
};

export type BookingHistoryItem = {
    id: number;
    fromStatus: string | null;
    toStatus: string;
    notes: string | null;
    actorName: string | null;
    createdAt: string | null;
};

export type BookingReview = {
    id: number;
    rating: number;
    comment: string | null;
    status: string;
    proofCount?: number;
    artisanResponse?: string | null;
    artisanRespondedAt?: string | null;
};

export type BookingDispute = {
    id: number;
    status: string;
    severity: string;
    subject: string;
    openedAt: string | null;
};

export type BookingPaymentSummary = {
    id: number;
    status: string;
    reference: string;
    amountDisplay: string;
    commissionDisplay?: string | null;
    providerFeeDisplay?: string | null;
    netAmountDisplay?: string | null;
    checkoutUrl?: string | null;
};

export type BookingAddressSnapshot = {
    line_1?: string | null;
    line_2?: string | null;
    landmark?: string | null;
    country_id?: number | null;
    state_id?: number | null;
    local_government_id?: number | null;
    territory_id?: number | null;
};

export type BookingDetail = {
    id: number;
    trackerCode: string;
    status: string;
    customerName: string;
    customerPhone?: string;
    customerEmail?: string | null;
    scheduledAt: string | null;
    description?: string | null;
    quotedAmount?: number | null;
    quotedAmountDisplay: string | null;
    currencyCode: string;
    address?: BookingAddressSnapshot;
    artisan: BookingArtisanSummary;
    service: BookingServiceSummary | null;
    canPay?: boolean;
    canChat?: boolean;
    histories?: BookingHistoryItem[];
    canReview?: boolean;
    canUpgrade?: boolean;
    canDispute?: boolean;
    payment?: BookingPaymentSummary | null;
    review?: BookingReview | null;
    disputes?: BookingDispute[];
};

export type ArtisanBookingItem = BookingDetail & {
    customerPhone: string;
    customerEmail: string | null;
    address: BookingAddressSnapshot;
};

export type BookingChatMessage = {
    id: number;
    senderRole: 'customer' | 'artisan';
    senderName: string;
    body: string;
    mine: boolean;
    createdAt: string | null;
};

export type BookingChatPage = {
    role: 'customer' | 'artisan';
    backUrl: string;
    currentTeamSlug: string | null;
    canSend: boolean;
    privacyNotice: string;
    booking: Pick<
        BookingDetail,
        'id' | 'trackerCode' | 'status' | 'customerName' | 'artisan' | 'service'
    >;
    messages: BookingChatMessage[];
};
