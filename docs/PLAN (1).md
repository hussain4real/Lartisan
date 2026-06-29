# Phased Lartisan Implementation Plan

## Summary
- Current state: all scoped implementation phases, Phase 0 through Phase 16, are complete, including marketplace, booking, payment, escrow, notifications, customer-depth, chat, support inbox, trust and moderation, automated payout operations, reporting, observability, recovery workflows, and opt-in proximity marketplace discovery.
- Build order: environment and quality gates, database foundations, backend action workflows, Inertia customer/artisan UI, Filament operations UI, then hardening and BRS expansion tracks.
- Locked decisions: PHP 8.5, Larastan with no baseline, 100% coverage after every phase, Teams represent artisan business workspaces, local OTP first, pilot geography seed, Paystack-first escrow, and Phase 10 notification channels are email and WhatsApp for now.

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

**Phase 9: Booking Payment And Escrow**
Goal: add booking checkout, escrow-aware lifecycle states, commission and fee snapshots, refunds, and Paystack-backed booking webhooks.
- DB: booking payment references, payment settlement snapshots, booking payment lifecycle timestamps, and payment/query indexes.
- Backend actions: `InitializeBookingPayment`, `CalculateBookingSettlement`, `EscrowBookingPayment`, `RefundBookingPayment`, and payment webhook handling for booking payments.
- Frontend: registered customer and guest tracker payment initiation from booking screens.
- Tests: checkout authorization, webhook idempotency, invalid amounts, failed payments, escrow ledger entries, settlement math, refunds, and invalid lifecycle transitions.

**Phase 10: Communication And Notifications**
Goal: implement transactional email and WhatsApp communication for booking, payment, review, subscription, payout, dispute, and support events.
- DB: notification delivery logs with provider status, callback metadata, attempts, failures, and dead-letter timestamps.
- Backend actions/interfaces: `NotificationProvider`, `SendNotificationDelivery`, `SendLifecycleNotification`, email and WhatsApp provider adapters, WhatsApp callback intake, retry and dead-letter commands.
- Frontend: no broad UI beyond existing event-triggering surfaces; operations visibility comes from delivery records and support workflows.
- Tests: provider fakes, disabled-provider fallback, failures, callback validation, retries, dead letters, and lifecycle event dispatch.
- Scope note: SMS, push, and richer in-app notification surfaces remain future BRS expansion unless explicitly approved.

**Phase 11: Customer Account Depth**
Goal: complete deeper guest and registered customer journeys around booking confidence and repeat use.
- DB: customer favorites and customer profile preferences.
- Backend actions: booking OTP enforcement, saved-address validation, guest account upgrade, guest tracker review/dispute submission, favorites, and preference persistence.
- Frontend: saved-address booking support, tracker account upgrade, guest review/dispute forms, favorite actions, and booking preference page.
- Tests: OTP branches, saved-address ownership, guest upgrade, token-limited guest review/dispute access, favorites, preferences, and denial paths.

**Phase 12: Chat And Support Inbox**
Goal: add controlled registered booking chat and dedicated support inbox workflows.
- DB: booking messages and support case notes.
- Backend actions/policies: `PostBookingMessage`, `BookingMessagePolicy`, support case assignment, internal notes, audited status transitions, and contact-detail safeguards.
- Frontend: shared customer/artisan booking chat UI and Filament support inbox list/view actions.
- Tests: chat visibility, authorization, eligible/closed booking states, contact blocking, support scoping, assignment, notes, status transitions, notifications, and audit logs.

**Phase 13: Trust And Moderation Expansion**
Goal: expand trust workflows beyond the scoped MVP loop.
- DB: review proof media, artisan review responses, moderation signal metadata, and expanded dispute targets where needed.
- Backend actions: review response handling, suspicious review detection/routing, profile/payment dispute creation, and audited dispute adjustment actions for money-changing outcomes.
- Frontend: review proof upload, artisan response UI, moderation queues, and profile/payment dispute entry points.
- Tests: moderation routing, media privacy, response permissions, suspicious-pattern handling, profile/payment dispute targets, and ledger-backed adjustment outcomes.

**Phase 14: Payout Automation And Finance Ops**
Goal: automate payout dispatch and strengthen finance recovery workflows.
- DB: provider transfer identifiers, reconciliation metadata, scheduled batch records if needed, bank/BVN verification status, and payout exception tracking.
- Backend actions/interfaces: payout provider contract, transfer dispatch, webhook or polling reconciliation, scheduled payout batch creation, verification checks, retry/reversal handling, and finance approval flows.
- Frontend: finance exception queues and safer payout status visibility for operations and artisans.
- Tests: provider failures, retries, duplicate/uncertain callbacks, reconciliation, no-double-pay guarantees, bank verification, and finance approval denial paths.

**Phase 15: Reporting, Observability, And Recovery**
Goal: make operations and production readiness visible before silent failures become business problems.
- Monitoring: use Laravel Nightwatch and Laravel logs as the approved monitoring sources for exceptions, queue failures, provider failures, and operational recovery signals.
- Backup: use `spatie/laravel-backup` for database and critical media backup execution, health checks, and restore-readiness verification.
- DB/config: provider health snapshots or report metrics where useful, queue/failed-job visibility, backup status metadata, retention settings, and restore-test tracking.
- Backend: health checks for payments, notifications, storage, payout provider, queues, database touchpoints, scheduler freshness, Laravel logs, Nightwatch handoff expectations, backup execution, and restore verification.
- Frontend: Super Admin Filament health and recovery dashboard only; State, LGA, Agent, and Artisan surfaces stay out of Phase 15 unless a later reporting phase requires them.
- Retention: keep audit logs, payment records, KYC records, wallet ledgers, admin actions, and dispute outcomes indefinitely by default; prune notification delivery logs, provider callback logs, health snapshots, and operational noise after configurable retention windows.
- Tests: Super Admin visibility, health-check status handling, provider-failure surfacing, failed-job visibility, backup status checks, restore-test tracking, and retention-policy guard rails.

**Phase 16: Location-Aware Marketplace Proximity Discovery**
Goal: make marketplace discovery prioritize nearby verified artisan services using opt-in browser location, similar to Facebook Marketplace location behavior.
- DB/data: persist verified artisan marketplace coordinates on artisan profiles from completed field visits with coordinates; keep the existing State, LGA, Territory, and service-radius model.
- Backend: validate marketplace `near_lat`, `near_lng`, and `radius_km` inputs, extend `SearchArtisans` for proximity ranking and radius filtering, emit approximate distance payloads, and keep manual geography filters authoritative.
- Frontend: provide a `Use my location` marketplace control, radius selector, clear-location state, permission-denied/unavailable fallback, distance labels on artisan cards, and the existing State/LGA/Territory filters.
- Privacy: round browser coordinates before request, use them only for the active search, and do not persist precise user coordinates to customer, profile, or session records.
- Types: include marketplace TypeScript filter and artisan-card fields for proximity filters and optional `distanceKm` / distance-label values.
- Tests: validate proximity query inputs, nearby ranking, manual-filter interaction, no coordinate persistence, field-visit coordinate publishing, geolocation success/denial/unavailable UI states, clear-location behavior, and radius changes.

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
- Phase 13 through Phase 16 are BRS expansion phases, not blockers for the scoped Phase 1-12 MVP completion.
- Phase 15 may add `spatie/laravel-backup`; do not add other observability or backup packages without a separate decision.
- Phase 16 must not add new third-party dependencies, and precise user coordinates must stay query-only unless a later privacy and retention decision explicitly approves persistence.
- References used: [BRS](/Users/amisha/www/lartisan/docs/lartisan_brs.md), [Technical Spec](/Users/amisha/www/lartisan/docs/Technical_Spec_Lartisan_App.md), [Larastan package](https://packagist.org/packages/larastan/larastan), [Inertia Forms + Wayfinder](https://inertiajs.com/docs/v3/the-basics/forms), [Wayfinder README](https://github.com/laravel/wayfinder/blob/main/README.md).
