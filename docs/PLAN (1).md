# Phased Lartisan Implementation Plan

## Summary
- Current state: Laravel/Inertia starter with Fortify, passkeys, Teams, Wayfinder, and no marketplace tables yet. Baseline verified: `91` tests, `100.0%` coverage.
- Build order: environment and quality gates, database foundations, backend action workflows, then Inertia customer/artisan UI and Filament operations UI.
- Locked decisions: PHP 8.5, install approved packages now, Larastan with no baseline, 100% coverage after every phase, Teams represent artisan business workspaces, local OTP first, pilot geography seed, Paystack-first escrow.

## Implementation Phases
**Phase 0: Platform And Quality Gate**
Goal: make the repo installable, analyzable, and CI-aligned before domain work.
- DB: none.
- Backend: align local/CI PHP to 8.5; install `larastan/larastan:^3.0`, Spatie Permission, Media Library, Laravel PDF, and Filament using stable compatible constraints only.
- Tooling: add `phpstan.neon.dist` with Larastan extension, `level: max`, paths for `app`, `routes`, `database`, and `tests`; add Composer `analyse` script and CI step.
- Frontend: confirm Wayfinder, Vite, and existing Inertia build still pass after package installs.
- Pause summary: package versions, PHP status, static analysis result, coverage result.

**Phase 1: Domain Foundation**
Goal: create the Lartisan data backbone and role/scope model.
- DB: countries, states, LGAs, territories, area-agent assignments, admin profiles, customer profiles, artisan profiles, audit logs, and pilot Nigeria seed data.
- Backend: backed enums for roles/statuses; Spatie role/permission seeders; policies and query scopes for platform/state/LGA/territory/owner visibility.
- Teams: preserve existing Teams, add a team type/kind so artisan business workspaces can coexist with personal teams.
- Frontend: no major UI beyond safe navigation/share-prop adjustments.
- Tests: migrations, factories, enum casts, role seeds, policy scoping, team compatibility.

**Phase 2: Identity, OTP, And Artisan Workspaces**
Goal: support customer/artisan onboarding and account claiming.
- DB: phone fields/status on users, OTP records, account-claim records, addresses.
- Backend actions: `IssueOtp`, `VerifyOtp`, `CreateCustomerProfile`, `CreateArtisanBusinessWorkspace`, `ClaimAgentCreatedAccount`.
- Frontend: Inertia onboarding/auth forms using Wayfinder route/action helpers and `<Form>`.
- Tests: OTP expiry/rate limits, claim flow, profile creation, validation, Inertia props.

**Phase 3: Artisan Profile, Catalog, And KYC Intake**
Goal: artisans can prepare a public listing and submit verification evidence.
- DB: service categories, artisan services, KYC submissions, field visits, status histories, media collections.
- Backend actions: `UpsertArtisanProfile`, `CreateArtisanService`, `SubmitKyc`, `AttachKycMedia`, `RecordFieldVisit`.
- Frontend: artisan dashboard/profile/KYC/catalog pages; private KYC uploads and public portfolio media.
- Tests: validation, media collection rules, status transitions, authorization, Inertia contracts.

**Phase 4: Operations Verification Panels**
Goal: Area Agents, LGA Admins, State Coordinators, and Super Admins can run verification.
- DB: reason codes and audit coverage for KYC decisions, territory reassignment, and suspensions.
- Backend actions: `AssignTerritory`, `ReviewKyc`, `ApproveKyc`, `ReturnKyc`, `RejectKyc`, `EscalateKyc`.
- Frontend: Filament panels for `/admin`, `/state`, `/lga`, `/agent` with scoped resources and queues.
- Tests: Filament access, scoped table queries, action authorization, audit entries.

**Phase 5: Subscriptions, Payments, Wallets**
Goal: establish paid listing activation and auditable money movement.
- DB: subscription plans, subscriptions, payments, provider webhook events, wallets, ledger entries, payout accounts.
- Backend actions/interfaces: `PaymentProvider`, `PaystackPaymentProvider`, `InitializePayment`, `ProcessPaystackWebhook`, `ActivateSubscription`, `PostWalletLedgerEntry`.
- Frontend: artisan subscription and wallet pages; Paystack checkout initiation.
- Tests: webhook signature/idempotency, plan activation, immutable ledger behavior, failed payment paths.

**Phase 6: Discovery And Booking Flow**
Goal: customers can find verified subscribed artisans and complete the booking lifecycle.
- DB: bookings, booking status histories, booking media, address snapshots.
- Backend actions: `SearchArtisans`, `CreateBooking`, `AcceptBooking`, `RejectBooking`, `StartBookingWork`, `FinishBookingWork`, `ConfirmBookingCompletion`, `ReleaseWalletBalance`.
- Frontend: marketplace home, service search, artisan profile, booking form, secure tracker, customer/artisan booking screens.
- Tests: guest and registered booking, geography/category ranking, status rules, wallet release, browser smoke for main flows.

**Phase 7: Reviews, Disputes, Payouts, Reports**
Goal: complete the MVP trust loop and operational oversight.
- DB: reviews, disputes, support cases, payouts, payout attempts, report snapshots where needed.
- Backend actions: `SubmitVerifiedReview`, `OpenDispute`, `ResolveDispute`, `RequestPayout`, `ApprovePayout`, `ProcessPayout`, `GenerateScopedReport`, `RenderDocument`.
- Frontend: review/dispute screens, payout request UI, Filament dispute/payout/report resources.
- Tests: paid-booking-only reviews, dispute escalation, payout retries, PDF renderer fake, scoped metrics.

**Phase 8: MVP Hardening**
Goal: make the full trust loop reliable enough for pilot use.
- Backend: queue jobs, scheduled commands, notification fakes/providers, rate limits, indexes, signed media URLs, provider failure logging.
- Frontend: mobile field-agent polish, empty/loading/error states, accessibility and browser smoke coverage.
- Tests: full suite, coverage, static analysis, frontend checks, webhook/retry edge cases.

## Interfaces And Patterns
- Every meaningful write uses an `App\Actions\{Domain}\...` action; controllers and Filament actions stay thin.
- Inertia forms and links use Wayfinder imports from `@/actions` or `@/routes`; avoid hardcoded URLs.
- Payment, OTP, notification, and PDF work behind interfaces so tests use fakes and providers can be swapped.
- Use PHP backed enums with TitleCase keys for statuses and roles, cast on models.

## Required Gates After Every Phase
- `composer analyse`
- `php artisan test --coverage --min=100 --compact`
- `npm run types:check`
- `npm run lint:check`
- `npm run format:check`
- `npm run build`
- After each phase, pause with: goal delivered, key files changed, tests run, coverage %, static analysis status, and recommended next phase.

## Assumptions
- Use Paystack via Laravel HTTP client first; add an SDK only if provider requirements force it.
- Use stable Composer packages only; do not lower `minimum-stability` without a new decision.
- Do not create extra documentation files during implementation unless explicitly requested.
- References used: [BRS](/Users/amisha/www/lartisan/docs/lartisan_brs.md), [Technical Spec](/Users/amisha/www/lartisan/docs/Technical_Spec_Lartisan_App.md), [Larastan package](https://packagist.org/packages/larastan/larastan), [Inertia Forms + Wayfinder](https://inertiajs.com/docs/v3/the-basics/forms), [Wayfinder README](https://github.com/laravel/wayfinder/blob/main/README.md).
