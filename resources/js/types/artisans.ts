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
