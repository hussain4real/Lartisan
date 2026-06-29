# Lartisan Implementation Progress Plan

Prepared: June 24, 2026

This plan tracks the implemented Lartisan MVP phases, completed BRS expansion phases, and future BRS follow-up. It follows the same phase-section format as the PowerX pending plan while preserving Lartisan's original phase numbering from `docs/PLAN (1).md`.

## Current Implementation Baseline

The application already includes the scoped marketplace and operations foundation:

- Phase 0 through Phase 16 from `docs/PLAN (1).md` are implemented for the scoped MVP and completed BRS expansion plan.
- Laravel, Inertia, Vue, Filament, Pest, Larastan, Pint, ESLint, Prettier, Vite, and CI-aligned quality gates are present.
- Geography, territories, admin profiles, customer profiles, artisan profiles, audit logs, roles, permissions, policies, and role-scoping tests are present.
- Customer and artisan onboarding, phone-aware identity records, OTP record foundations, OTP-at-booking, account claims, customer addresses, saved-address booking, artisan workspace creation, guest account upgrade, guest tracker review/dispute flows, and Inertia route contracts are present.
- Artisan public profile setup, service categories, artisan services, portfolio media, private KYC evidence, field visits, verification status history, and artisan-facing validation flows are present.
- Filament operations panels exist for Area Agent, LGA Admin, State Coordinator, and Super Admin verification workflows.
- Subscription plans, Paystack subscription checkout, subscription webhooks, provider event idempotency, wallets, immutable ledger entries, payout account foundations, booking payment checkout, booking escrow, commission/provider-fee snapshots, refund adjustments, and net settlement ledgers are present.
- Marketplace search and filtering, public artisan profiles, guest and registered booking requests, secure booking tracker, customer booking screens, artisan booking screens, controlled booking chat, browser-smoke coverage, and scoped booking lifecycle actions are present.
- Verified reviews, disputes, support case foundations, support inbox assignment/internal-note workflows, manual payout requests, payout approval and processing, payout attempts, report snapshots, PDFs, and scoped report tests are present.
- Transactional email and WhatsApp notification abstractions, delivery logs, provider callback intake, retry/dead-letter workflows, booking/subscription/payout/review/dispute/support notifications, customer favorites, and customer booking preferences are present.
- Review proof media, artisan responses, moderation routing, profile/payment dispute targets, Paystack-backed payout automation, finance exception queues, provider reconciliation, system-health snapshots, backup/recovery checks, retention guard rails, and opt-in proximity marketplace discovery are present.

The scoped implementation now covers the planned Phase 0 through Phase 16 requirements. Remaining BRS follow-up is limited to product, provider, or policy decisions intentionally kept outside the current shipped scope, such as SMS/push/richer in-app notification expansion, role dashboards beyond the scoped Super Admin recovery dashboard, Agent compensation policy, Flutterwave expansion beyond Paystack, and launch-specific operating rules.

## Implementation Principles

- Keep Laravel action classes as the primary home for business rules.
- Use policies, scopes, and dedicated tests for every role-sensitive workflow.
- Use Filament actions and resources for staff workflows that need auditability.
- Use Inertia/Vue routes for public, customer, and artisan workflows.
- Keep Paystack, SMS, WhatsApp, email delivery, payout provider, monitoring, and storage-provider behavior behind configuration and provider sign-off.
- Add or update feature tests for every workflow change, including denial paths, audit behavior, provider fakes, and ledger behavior where relevant.
- Do not mark the full BRS complete just because the scoped MVP phases are implemented.

## Phase 0: Platform And Quality Gate

### Status

Completed and verified.

### Goal

Make the repository installable, analyzable, testable, and CI-aligned before domain work.

### BRS Coverage

- Application installability and runtime baseline.
- Static analysis, formatting, test coverage, and frontend build expectations.
- Repeatable quality gates for future phases.

### Checklist

- [x] Establish the PHP 8.5 and Laravel package baseline.
- [x] Add and verify the Composer analysis gate.
- [x] Add and verify frontend type, lint, format, and build gates.
- [x] Keep backend tests running with a 100% coverage gate.
- [x] Record the gate commands required after every future phase.

### Deliverables

- Laravel, Inertia, Vue, Filament, Pest, Larastan, Pint, ESLint, Prettier, and Vite baseline.
- CI-aligned local quality gates.
- Coverage and analysis expectations for future work.

### Acceptance Criteria

- `composer analyse`, `php artisan test --coverage --min=100 --compact`, `npm run types:check`, `npm run lint:check`, `npm run format:check`, and `npm run build` pass at the recorded baseline.
- Future phases have a clear verification standard before they can be marked complete.

## Phase 1: Domain Foundation

### Status

Completed and verified.

### Goal

Create the Lartisan data backbone and role/scope model.

### BRS Coverage

- Country, state, local government, territory, and operational geography foundations.
- Admin profile, customer profile, and artisan profile foundations.
- Role, permission, policy, and audit foundations.

### Checklist

- [x] Add geography and territory data structures.
- [x] Add admin, customer, and artisan profile foundations.
- [x] Add team kind support where the application needs scoped team behavior.
- [x] Add audit log support for sensitive operational actions.
- [x] Add role seeds, policies, and scoping tests for operational boundaries.

### Deliverables

- Geography, territories, admin profiles, customer profiles, artisan profiles, audit logs, team kind support, roles, policies, and scoping tests.

### Acceptance Criteria

- Role-scoped users can only see and act on records inside their intended territory or permission boundary.
- The domain foundation supports later onboarding, verification, marketplace, booking, reporting, and payout workflows.

## Phase 2: Identity, OTP, And Artisan Workspaces

### Status

Completed and verified for scoped onboarding and account-claiming behavior.

### Goal

Support customer and artisan onboarding, phone-aware identity, account claiming, and workspace creation.

### BRS Coverage

- Customer and artisan account setup.
- Phone and OTP record foundations.
- Artisan workspace creation and account claim flow.
- Customer address data support.

### Checklist

- [x] Add phone fields and OTP record support.
- [x] Add customer profile creation.
- [x] Add artisan workspace creation.
- [x] Add account claim support.
- [x] Add address data support for registered customers.
- [x] Add Inertia route contracts and tests for the scoped identity flows.

### Deliverables

- Phone fields, OTP records, account claims, addresses, customer profile creation, artisan workspace creation, claim flow, and Inertia contract coverage.

### Acceptance Criteria

- Customers and artisans can move through the scoped onboarding paths.
- Account claims and workspace creation are tested.
- OTP-at-booking remains tracked under Phase 11.

## Phase 3: Artisan Profile, Catalog, And KYC Intake

### Status

Completed and verified.

### Goal

Allow artisans to prepare public listings and submit verification evidence.

### BRS Coverage

- Artisan public profile data.
- Service categories and artisan services.
- Portfolio and private verification media.
- KYC intake and field visit records.
- Status history for verification workflows.

### Checklist

- [x] Add service category and artisan service foundations.
- [x] Add artisan profile and listing fields required for marketplace readiness.
- [x] Add KYC submission support.
- [x] Add field visit and verification status history records.
- [x] Add media collections for portfolio and private verification evidence.
- [x] Add artisan-facing pages and validation tests.

### Deliverables

- Service categories, artisan services, KYC submissions, field visits, status histories, media collections, artisan pages, and validation tests.

### Acceptance Criteria

- Artisans can prepare catalog/profile data and submit verification evidence.
- Public media and private evidence are separated by intended collection and disk behavior.
- KYC intake records are ready for operations review.

## Phase 4: Operations Verification Panels

### Status

Completed and verified.

### Goal

Allow Area Agents, LGA Admins, State Coordinators, and Super Admins to run verification and scoped operations.

### BRS Coverage

- Scoped staff panels for verification workflows.
- KYC review queues and verification actions.
- Reason codes, suspensions, reassignment, and audit coverage.
- Operational oversight across geography scopes.

### Checklist

- [x] Add Filament panels for the scoped operations roles.
- [x] Add KYC queues and scoped resources.
- [x] Add reason codes for verification outcomes.
- [x] Add verification actions and audit records.
- [x] Add territory reassignment and suspension support.
- [x] Add tests for panel access, scoping, and sensitive actions.

### Deliverables

- Filament panels, KYC queues, scoped resources, reason codes, verification actions, territory reassignment, suspensions, and audit coverage.

### Acceptance Criteria

- Operations users can only review and act within their allowed scope.
- Verification decisions leave an auditable record.
- Suspensions and reassignments are covered by tests.

## Phase 5: Subscriptions, Payments, Wallets

### Status

Completed and verified for subscription payments and wallet foundations.

### Goal

Establish paid listing activation and auditable money movement.

### BRS Coverage

- Subscription plan selection and payment initialization.
- Paystack subscription webhooks and provider event idempotency.
- Wallet and ledger foundations.
- Payout account foundation.

### Checklist

- [x] Add subscription plans.
- [x] Add subscription payment records.
- [x] Add Paystack initialization for subscription checkout.
- [x] Add provider webhook handling and idempotency.
- [x] Add wallets and immutable ledger entries.
- [x] Add payout account foundations and wallet tests.

### Deliverables

- Subscription plans, subscription payments, Paystack initialization and webhooks, provider event idempotency, wallets, ledger entries, payout accounts, and wallet tests.

### Acceptance Criteria

- Subscription checkout can activate the intended listing behavior.
- Provider events are idempotent.
- Money movement has auditable ledger entries.
- Booking payment, escrow, commissions, refunds, and settlement policy are completed under Phase 9.

## Phase 6: Discovery And Booking Flow

### Status

Completed and verified. Browser-smoke coverage was added during Phase 8 hardening.

### Goal

Let customers find verified subscribed artisans and complete the scoped booking lifecycle.

### BRS Coverage

- Marketplace search and filtering.
- Public artisan profile discovery.
- Guest and registered booking request creation.
- Secure booking tracker.
- Customer and artisan booking screens.
- Booking status actions and wallet release tests.

### Checklist

- [x] Add marketplace search and filters.
- [x] Add public artisan profile routes.
- [x] Add booking request form and creation flow.
- [x] Add guest and registered booking support.
- [x] Add secure booking tracker.
- [x] Add customer and artisan booking screens.
- [x] Add booking status actions and wallet release tests.
- [x] Persist browser-smoke coverage for the main public and booking flows.

### Deliverables

- Marketplace search, filtering, public profile, booking request form, guest/registered booking, secure tracker, customer/artisan booking screens, status actions, and wallet release tests.

### Acceptance Criteria

- Customers can discover eligible artisans and start bookings.
- Customers and artisans can use the scoped booking lifecycle screens.
- Route and Inertia contract coverage is present.
- Browser-smoke coverage is tracked under Phase 8.

## Phase 7: Reviews, Disputes, Payouts, Reports

### Status

Completed and verified for the scoped MVP trust and reporting loop.

### Goal

Complete the scoped MVP trust loop and operational oversight.

### BRS Coverage

- Verified reviews.
- Dispute and support case foundations.
- Manual payout request, approval, processing, and attempt tracking.
- Report snapshots and PDFs.
- Scoped operations visibility.

### Checklist

- [x] Add verified review support.
- [x] Add dispute and support case foundations.
- [x] Add payout request, approval, processing, and attempt tracking.
- [x] Add report snapshots and PDF rendering.
- [x] Add Filament resources for the operational surfaces.
- [x] Add scoped report tests.

### Deliverables

- Verified reviews, disputes, support cases, payout requests/approval/processing, payout attempts, report snapshots, PDF rendering, Filament resources, and scoped report tests.

### Acceptance Criteria

- Reviews are tied to eligible booking behavior.
- Disputes and support cases can be tracked.
- Payout requests can be manually reviewed and processed.
- Reports and PDFs are covered by scoped tests.
- Guest reviews, suspicious review detection, proof media, artisan review responses, profile/payment dispute targets, and provider-backed payout automation remain future work.

## Phase 8: MVP Hardening

### Status

Completed and verified on June 24, 2026.

### Goal

Close launch-hardening gaps before relying on the MVP in a production-like environment.

### BRS Coverage

- Queue and scheduler readiness.
- Rate limits and operational abuse protection.
- Marketplace, booking, verification, payout, webhook, and reporting query readiness.
- Private media access behavior.
- Provider failure visibility.
- Mobile, accessibility, and browser-smoke confidence for high-traffic routes.

### Checklist

- [x] Confirm required queues, scheduled commands, and worker commands.
- [x] Add notification fakes or provider abstractions needed by tests.
- [x] Add rate limits for sensitive public and auth-adjacent workflows.
- [x] Add indexes for marketplace, booking, verification, payout, webhook, and report-adjacent queries.
- [x] Verify signed media URL behavior for private media.
- [x] Add provider failure logging for storage, payment, and future notification providers.
- [x] Complete mobile polish and accessibility review for high-traffic routes.
- [x] Add browser-smoke coverage for marketplace, booking, tracker, customer booking, artisan booking, and operations panel entry points.

### Deliverables

- Named throttles for waitlist, marketplace booking, tracker confirmation, OTP, account claim, Paystack webhook, and artisan media upload workflows.
- Scheduled queue maintenance for failed jobs and batches using shared scheduler locks.
- Laravel Cloud worker and scheduler command config.
- Private KYC media temporary signed URLs.
- Generic provider failure logging used by storage and Paystack.
- Forward migration with marketplace, booking, verification, payout, and webhook processing indexes.
- Focused Phase 8 feature tests and Pest browser smoke tests.

### Acceptance Criteria

- Main flows have browser-smoke coverage through `composer browser:smoke`.
- Hardening items are tested by `php artisan test --compact tests/Feature/PhaseEightHardeningTest.php`.
- Static analysis, frontend type checking, linting, formatting, full coverage, and production build gates pass.

## Phase 9: Booking Payment And Escrow

### Status

Completed and verified on June 24, 2026.

### Goal

Add booking payments, escrow-aware booking lifecycle states, commission and fee calculation, refunds and adjustments, and Paystack-backed booking checkout/webhooks.

### BRS Coverage

- Booking checkout and payment purpose.
- Paid, escrowed, settled, refunded, and reviewed booking states.
- Commission, provider-fee, refund, and net settlement policy.
- Provider webhook handling for booking payments.
- Ledger entries for gross escrow, commission, provider fees, refunds, adjustments, and net artisan settlement.

### Checklist

- [x] Add booking payment purpose and provider references.
- [x] Add booking payment initialization through Paystack for registered customers and guest tracker users.
- [x] Add booking payment webhook handling and idempotency.
- [x] Add paid, escrowed, settled, refunded, and reviewed lifecycle states where required.
- [x] Add commission and platform/provider-fee calculation.
- [x] Add refund and adjustment ledger entries.
- [x] Add tests for webhook denial paths, duplicate events, invalid amounts, invalid lifecycle transitions, refund behavior, and settlement math.

### Deliverables

- Booking payment schema fields and settlement snapshots.
- Paystack booking checkout initialization for customer and tracker flows.
- Booking payment webhook processing with idempotent escrow behavior.
- Escrow lifecycle updates for paid, escrowed, settled, refunded, and reviewed booking states.
- Config-driven commission and provider-fee calculation.
- Pending escrow ledger entries for gross booking amount, commission, and provider fees.
- Net settlement ledger entries for artisan wallet release.
- Refund adjustment and refund ledger entries.
- Focused Phase 9 feature tests for checkout, webhook, escrow, settlement, refund, and denial paths.

### Acceptance Criteria

- Customers can pay for bookings through provider checkout.
- Guest tracker users can pay for eligible bookings without exposing unrelated booking data.
- Webhooks update payment and booking state exactly once.
- Wallet entries record gross escrow, commission, provider fees, refunds, adjustments, and net settlement.
- Invalid, duplicate, failed, pending, wrong-purpose, or amount-mismatched provider events do not corrupt booking or ledger state.
- Focused feature coverage passed with `php artisan test --compact tests/Feature/PhaseNineBookingPaymentEscrowTest.php`.
- The full suite passed `php artisan test --coverage --min=100 --compact` with 100.0% coverage.

## Phase 10: Communication And Notifications

### Status

Completed and verified on June 25, 2026.

### Goal

Implement transactional communication across booking, payment, review, subscription, payout, and support events.

### BRS Coverage

- SMS, WhatsApp, email, and in-app notification delivery where approved.
- Delivery logs and provider status tracking.
- Booking status notifications.
- Subscription reminders.
- Payout notifications.
- WhatsApp webhook intake.

### Checklist

- [x] Confirm required notification channels and providers (Email and WhatsApp).
- [x] Add notification provider contracts behind configuration.
- [x] Add delivery log records for outbound and provider callback events.
- [x] Add booking status notifications.
- [x] Add subscription reminder notifications.
- [x] Add payout notifications.
- [x] Add review, dispute, and support-case notifications.
- [x] Add WhatsApp webhook intake where provider behavior is approved.
- [x] Add failure visibility and retry/dead-letter workflows where needed.

### Deliverables

- Notification contracts and provider adapters where approved.
- Delivery log records for outbound notification attempts and provider callbacks.
- Booking, subscription, payout, review, dispute, and support-case notifications.
- WhatsApp webhook intake where provider details are signed off.
- Failure visibility, retry, and dead-letter review workflow.
- Tests for provider send, disabled-provider fallback, failures, callback validation, retries, and event dispatch.

### Acceptance Criteria

- Required events dispatch through provider abstractions.
- Delivery attempts and provider results are logged.
- Provider failures are visible to operations through failed and dead-lettered delivery records.
- Tests run with notification and HTTP fakes without depending on live providers.
- Focused feature coverage passed with `php artisan test --compact tests/Feature/PhaseTenCommunicationNotificationTest.php`.
- The full suite passed `php artisan test --coverage --min=100 --compact` with 100.0% coverage.

## Phase 11: Customer Account Depth

### Status

Completed and verified on June 25, 2026.

### Goal

Complete the deeper customer journey for guest and registered customers.

### BRS Coverage

- OTP-at-booking.
- Saved-address booking.
- Guest account upgrade.
- Guest review and dispute flows.
- Favorites and customer booking preferences.

### Checklist

- [x] Add OTP-at-booking for guest and customer flows where required.
- [x] Expose saved-address selection in the booking flow.
- [x] Add guest account upgrade flow after booking.
- [x] Add guest review submission where booking eligibility permits.
- [x] Add guest dispute submission where booking eligibility permits.
- [x] Add favorites and customer booking preferences.
- [x] Add tests for guest and registered customer denial paths.

### Deliverables

- Booking OTP endpoint and verification action for guest and changed-phone customer bookings.
- Saved-address booking support with server-side address ownership validation.
- Guest tracker account upgrade action that creates a customer account, personal team, profile, default address, and attaches the booking.
- Guest tracker review and dispute submission routes, forms, token checks, support-case creation, and private evidence media support.
- Customer favorite model/table/routes and booking preference routes/page backed by customer profile preferences.
- Focused Phase 11 feature tests for OTP, saved addresses, guest upgrades, guest reviews, guest disputes, favorites, preferences, and denial paths.

### Acceptance Criteria

- Guest and registered customer flows satisfy the BRS customer journey.
- OTP, saved addresses, favorites, guest upgrades, guest reviews, and guest disputes are covered by tests.
- Guest access remains limited to the intended booking or tracker context.
- Focused feature coverage passed with `php artisan test --compact tests/Feature/PhaseElevenCustomerAccountDepthTest.php`.
- Adjacent booking, trust, payment, and customer-depth flows passed with `php artisan test --compact tests/Feature/PhaseSixDiscoveryBookingFlowTest.php tests/Feature/PhaseSevenTrustLoopTest.php tests/Feature/PhaseNineBookingPaymentEscrowTest.php tests/Feature/PhaseElevenCustomerAccountDepthTest.php`.

## Phase 12: Chat And Support Inbox

### Status

Completed and verified on June 25, 2026.

### Goal

Add controlled booking chat and dedicated support inbox workflows.

### BRS Coverage

- Registered customer and artisan chat during eligible booking states.
- Support inboxes and assignment views.
- Contact and privacy safeguards.

### Checklist

- [x] Define eligible booking states for chat.
- [x] Add chat message model, policies, and routes.
- [x] Add customer and artisan chat UI.
- [x] Add support inbox views and assignment workflow.
- [x] Add support-case internal notes and status transitions as needed.
- [x] Add contact/privacy safeguards.
- [x] Add tests for visibility, authorization, and closed-booking behavior.

### Deliverables

- Booking chat model, enum, policy, action, route, rate limit, validation request, and shared Inertia chat UI for registered customer and artisan participants.
- Chat eligibility for requested, accepted, paid, escrowed, and in-progress registered bookings, with closed bookings readable but not writable.
- Privacy validation blocking emails, URLs, and phone-like contact details in chat messages.
- Filament support inbox resource with scoped list/view access, assignment action, internal notes, and audited status transitions.
- Focused Phase 12 feature tests covering chat visibility, authorization, contact blocking, guest/closed-booking behavior, support scoping, assignment, notes, status transitions, notifications, and audit logs.

### Acceptance Criteria

- Eligible booking states allow controlled chat.
- Support teams can triage cases in dedicated inboxes.
- Users cannot access unrelated conversations or hidden support notes.
- Focused feature coverage passed with `php artisan test --compact tests/Feature/PhaseTwelveChatSupportInboxTest.php`.
- Static analysis and frontend lint, format, and type checks passed for the Phase 12 implementation.

## Phase 13: Trust And Moderation Expansion

### Status

Completed and verified on June 26, 2026.

### Goal

Expand review, dispute, and moderation capabilities beyond the scoped MVP trust loop.

### BRS Coverage

- Proof-of-work review media.
- Artisan review responses.
- Suspicious review detection.
- Profile and payment dispute targets.
- Money-changing dispute adjustments.

### Checklist

- [x] Add review proof media where required.
- [x] Add artisan review response workflow.
- [x] Add suspicious review detection and routing.
- [x] Add profile dispute target support.
- [x] Add payment dispute target support.
- [x] Add audited ledger adjustments when dispute outcomes change money movement.
- [x] Add tests for moderation routing, media visibility, responses, and money-changing outcomes.

### Deliverables

- Private review proof media collection with customer and guest upload validation.
- Artisan review response action, route, audit trail, and booking UI display.
- Suspicious-review scoring for low ratings, configured keywords, missing-comment low ratings, and review velocity.
- Review moderation routing into support cases plus Super Admin/scoped operations Filament review moderation.
- Profile, review, booking, and payment dispute target tracking.
- Audited dispute money-adjustment ledger entries using immutable wallet ledger references.
- Focused Phase 13 feature tests for proof media, moderation routing, Filament moderation actions, artisan responses, expanded dispute targets, and ledger-backed dispute adjustments.

### Acceptance Criteria

- Reviews and disputes cover media, responses, moderation signals, and profile/payment targets.
- Money-changing dispute outcomes are audited and ledger-backed.
- Suspicious review routing is visible to the correct operations role.
- Focused feature coverage passed with `php artisan test --compact tests/Feature/PhaseThirteenTrustModerationExpansionTest.php`.

## Phase 14: Payout Automation And Finance Ops

### Status

Implemented. Manual payout foundations now have Paystack-backed automation, scheduled batches, webhook and polling reconciliation, finance exception queues, and no-double-pay recovery safeguards.

### Goal

Automate payout dispatch and strengthen finance operations.

### BRS Coverage

- Provider transfer dispatch.
- Payout webhooks or polling.
- Scheduled payout batches.
- Bank and BVN verification where available.
- Finance review queues and payout exception recovery.
- Artisan payout status visibility without exposing sensitive payout-account internals.

### Checklist

- [x] Confirm payout provider capabilities and required compliance fields.
- [x] Add provider transfer dispatch.
- [x] Add payout webhook or polling reconciliation.
- [x] Add scheduled payout batches.
- [x] Add bank and BVN verification where available.
- [x] Add finance review queues for exceptions.
- [x] Add payout recovery workflows for failed, reversed, duplicate, or uncertain transfers.
- [x] Add tests for provider failures, retries, reconciliation, and finance approvals.

### Deliverables

- Paystack payout provider contract and implementation for bank resolve, transfer recipient registration, transfer dispatch, transfer verification, and webhook signature validation.
- Payout batch persistence and scheduled `payouts:dispatch-approved` automation.
- `payouts:reconcile-processing` polling for stale or uncertain transfers.
- Paystack transfer webhook reconciliation for success, failed, and reversed transfers.
- Finance exception queues through payout support cases for failed, reversed, uncertain, missing-debit, and action-required transfers.
- Artisan wallet payout status details with provider status, tracking reference, and lifecycle timestamps while hiding recipient codes.

### Acceptance Criteria

- Payouts can run through provider-backed automation.
- Scheduled batches respect finance rules and verified accounts.
- Failed or uncertain transfers are recoverable without double-paying artisans.
- Focused Phase 14 coverage passed with `php artisan test --compact tests/Feature/PhaseFourteenPayoutAutomationTest.php`.

## Phase 15: Reporting, Observability, And Recovery

### Status

Completed and verified on June 26, 2026.

### Goal

Add deeper operational reporting, observability, and recovery readiness.

### Implementation Clarifications

- Monitoring provider is Laravel Nightwatch plus Laravel application logs.
- Backup implementation should use `spatie/laravel-backup`.
- Health dashboard scope is Super Admin Filament only for Phase 15.
- Retention defaults should be conservative: keep audit logs, payment records, KYC records, wallet ledgers, admin actions, and dispute outcomes indefinitely unless policy says otherwise; prune notification delivery logs, provider callback logs, health snapshots, and operational noise after configurable retention windows.

### BRS Coverage

- Provider health.
- Queue health.
- Laravel Nightwatch, operational logs, and error monitoring.
- Backup, restore testing, and retention policies.
- Super Admin recovery visibility.

### Checklist

- [x] Add a Super Admin Filament health dashboard for providers, queues, failed jobs, storage, database, scheduler freshness, backup status, and recovery signals.
- [x] Add queue health and failed-job visibility with links or guidance for Laravel logs and operational recovery.
- [x] Add provider health checks for Paystack payments, email, WhatsApp, storage, and payout-provider readiness.
- [x] Define Laravel Nightwatch and Laravel log expectations for exception triage, provider failures, and recovery handoff.
- [x] Install and configure `spatie/laravel-backup` for database and critical media backups.
- [x] Add backup health/status checks and restore-test tracking for critical data and media.
- [x] Define conservative retention policies for sensitive records and configurable pruning for operational/provider noise.
- [x] Add tests for Super Admin visibility, health checks, failed-job visibility, backup status, restore tracking, and retention guard rails.

### Deliverables

- Super Admin-only Filament health and recovery dashboard with manual snapshot collection.
- System health snapshots covering database, queue/failed jobs, provider readiness, storage, scheduler heartbeat, backup health, restore-test freshness, Nightwatch/log sources, and retention guard rails.
- `operations:collect-health` and `operations:prune-noise` Artisan commands.
- Scheduled scheduler heartbeat, hourly health snapshot collection, backup run/monitor/clean jobs, and operational-noise pruning.
- `spatie/laravel-backup` configuration for database and critical private/public media backups.
- Restore-test record tracking for database and critical media verification.
- Conservative retention configuration that keeps sensitive audit, payment, KYC, wallet, admin, review, payout, and dispute records while pruning configured operational noise.
- Focused Phase 15 feature tests for health collection, dashboard access/action behavior, backup health, restore-test validation, scheduler registration, and retention pruning.

### Acceptance Criteria

- The Super Admin dashboard includes the required health and recovery signals.
- Laravel Nightwatch and Laravel logs are the documented monitoring sources for exceptions, provider failures, and recovery handoff.
- `spatie/laravel-backup` backup, restore-test, and retention checks are documented and verified.
- Operations can see provider, queue, and system failures before they become silent data issues.
- Focused feature coverage passed with `php artisan test --compact tests/Feature/PhaseFifteenReportingObservabilityRecoveryTest.php`.

## Phase 16: Location-Aware Marketplace Proximity Discovery

### Status

Completed and verified on June 28, 2026.

### Goal

Add opt-in location-aware marketplace discovery so users can see nearby verified artisan services ranked by proximity while keeping the existing manual State, LGA, and Territory filters.

### Implementation Clarifications

- Location source is opt-in browser geolocation.
- Marketplace query inputs are `near_lat`, `near_lng`, and `radius_km`.
- Manual category, State, LGA, and Territory filters remain visible and authoritative.
- Verified artisan marketplace coordinates are persisted on artisan profiles from completed field visits that include coordinates.
- Precise user coordinates are rounded before request, used only for the active marketplace search, and not persisted to customer, profile, or session records.
- No new third-party dependencies were added; the implementation uses the browser Geolocation API and app-side distance calculation compatible with existing tests.

### BRS Coverage

- Location-aware artisan recommendations.
- Prioritization of verified active artisans within the customer's nearby area or declared service radius.
- Marketplace search by category, keyword, location, availability, rating, price, and relevance without removing manual location filters.
- Privacy-safe geolocation behavior for customer discovery.

### Checklist

- [x] Add verified artisan marketplace coordinates from completed field-visit data.
- [x] Extend marketplace request validation for `near_lat`, `near_lng`, and `radius_km`.
- [x] Extend `SearchArtisans` for proximity ranking, radius filtering where appropriate, and manual-filter interaction.
- [x] Add approximate distance payloads for artisan cards when proximity search is active.
- [x] Extend marketplace TypeScript filter and artisan-card types for proximity inputs and optional distance fields.
- [x] Add marketplace UI controls for `Use my location`, radius selection, clear-location state, permission-denied fallback, unavailable-geolocation fallback, and distance labels.
- [x] Add tests for malformed proximity input, nearby ranking, manual filters, no coordinate persistence, browser geolocation states, clearing location, and radius changes.

### Deliverables

- Marketplace proximity query contract using `near_lat`, `near_lng`, and `radius_km`.
- Artisan marketplace coordinate persistence backed by completed field visits with coordinates.
- Privacy-safe browser-location workflow that rounds browser coordinates and does not persist precise user coordinates.
- Nearby artisan ranking, service-radius filtering, and distance display for verified active marketplace results.
- Existing manual location filters preserved as explicit user controls.
- Focused Phase 16 feature and UI/browser tests.

### Acceptance Criteria

- Users can opt in to current-location marketplace discovery.
- Nearby verified active artisan services rank ahead of farther artisans when proximity search is active.
- Manual State, LGA, Territory, category, and keyword filters continue to work with or without proximity search.
- Marketplace cards can show approximate distance when proximity is active.
- Declined or unavailable geolocation falls back cleanly to manual location filters.
- No precise user coordinates are persisted.
- Focused Phase 16 feature coverage passed with `php artisan test --compact tests/Feature/PhaseSixteenMarketplaceProximityTest.php`.
- Phase 16 browser coverage passed with `php artisan test --compact tests/Browser/PhaseSixteenMarketplaceProximityBrowserTest.php`.
- Filament field-visit coordinate capture is covered by the Phase 4 verification action test.
- The full coverage gate passed with `php artisan test --coverage --min=100 --compact` at 100.0% coverage.
- Static analysis and frontend type, lint, format, and production build gates passed for the Phase 16 implementation.

## Suggested Phase Order

Phase 0 through Phase 16 now have implementation coverage. No additional implementation phase is currently queued; future BRS expansion should be added as Phase 17 or later after product, provider, and policy sign-off.

## Global Verification Gate

Each phase should finish with:

- Focused feature tests for new workflows and denial paths.
- Permission tests for every role touched by the phase.
- Audit-event assertions for sensitive actions.
- Ledger assertions for every money-changing action.
- Provider fake tests for payment, notification, storage, and payout integrations.
- Browser-smoke coverage when public, customer, artisan, or operations UI flows change.
- `vendor/bin/pint --dirty --format agent` if PHP files changed.
- Minimum relevant `php artisan test --compact ...` test run for the changed area.
- `composer analyse` when PHP domain behavior changes.
- `php artisan test --coverage --min=100 --compact` before a phase is marked complete.
- Frontend type, lint, format, build, or browser verification when Inertia/Vue pages changed.
