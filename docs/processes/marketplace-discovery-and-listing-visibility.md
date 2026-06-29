# Marketplace Discovery And Listing Visibility

Status: current through Phase 16 implementation.

This process explains which artisans appear publicly and how marketplace search ranks results.

## Listing Eligibility

An artisan can appear in marketplace discovery only when all of the following are true:

- Verification status is `approved`.
- Subscription status is `active`.
- The artisan profile is public.
- The artisan has active services that match the requested category or search term.
- Availability allows discovery. Vacation availability prevents new marketplace discovery.

## Search Inputs

Marketplace search supports:

- Keyword search against business and service data.
- Category filter.
- State filter.
- LGA filter.
- Territory filter.
- Opt-in proximity inputs: `near_lat`, `near_lng`, and `radius_km`.

Manual geography filters remain authoritative. When State, LGA, or Territory filters are active, proximity ranking works within those filters rather than replacing them.

## Proximity Discovery

1. User clicks `Use my location` in the marketplace.
2. Browser asks for location permission.
3. If permission is granted, the browser sends rounded coordinates for the active search only.
4. The backend validates latitude, longitude, and radius.
5. Verified active artisans with marketplace coordinates are ranked by distance and service match.
6. Artisan cards can display approximate distance labels.
7. The user can change radius, clear location, or continue with manual filters.

## Coordinate Source

- Verified marketplace coordinates are stored on artisan profiles from completed field visits that include coordinates.
- Pilot and catalog seed data include verified demo coordinates for local and staging smoke coverage.
- Precise customer browser coordinates are not stored on customer, profile, or session records.

## Failure Modes

- Permission denied: show a fallback message and keep manual filters available.
- Geolocation unavailable: keep manual filters available.
- Missing artisan coordinates: artisan can still be found by manual geography filters, but cannot participate in proximity ranking until coordinates exist.
- Invalid proximity values: reject the request with validation errors rather than guessing.
