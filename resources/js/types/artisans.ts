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
    recipientCode: string | null;
    status: string;
    verifiedAt: string | null;
};
