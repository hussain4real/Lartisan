# Lartisan Implementation Progress

Verification date: 2026-06-11

## Summary

Phase 0 through Phase 7 are implemented for the scoped MVP plan in `docs/PLAN (1).md`. The current implementation includes the expected Laravel/Inertia/Vue/Filament surfaces, domain tables, actions, policies, routes, seeders, and phase-specific Pest coverage.

The current plan does not cover every requirement in `docs/lartisan_brs.md`. The BRS still has pending scope around booking payments and escrow, full transactional notifications, chat, OTP-at-booking, saved-address booking, guest review/dispute flows, payout automation, provider transfer callbacks, deeper trust tooling, observability, and data recovery.

Fresh verification gates passed:

| Gate | Result |
| --- | --- |
| Phase 1-7 focused Pest suite | Passed: 54 tests, 1,331 assertions |
| Full Pest suite with coverage | Passed: 191 tests, 2,105 assertions, 100.0% coverage |
| Static analysis | Passed: `composer analyse` |
| TypeScript | Passed: `npm run types:check` |
| ESLint | Passed: `npm run lint:check` |
| Prettier | Passed: `npm run format:check` |
| Production build | Passed: `npm run build` |

## Completed Phase Tracker

| Phase | Status | Goal | Evidence |
| --- | --- | --- | --- |
| Phase 0: Platform And Quality Gate | Implemented | Make the repo installable, analyzable, and CI-aligned before domain work. | PHP 8.5 package baseline, Larastan config, Composer analysis script, CI coverage gate, frontend checks, and production build are present and passing. |
| Phase 1: Domain Foundation | Implemented | Create the Lartisan data backbone and role/scope model. | Geography, territories, admin profiles, customer profiles, artisan profiles, audit logs, team kind support, role seeds, policies, and scoping tests are present. |
| Phase 2: Identity, OTP, And Artisan Workspaces | Implemented | Support customer/artisan onboarding and account claiming. | Phone fields, OTP records, account claims, addresses, customer profile creation, artisan workspace creation, claim flow, and Inertia contracts are covered. |
| Phase 3: Artisan Profile, Catalog, And KYC Intake | Implemented | Allow artisans to prepare public listings and submit verification evidence. | Service categories, artisan services, KYC submissions, field visits, status histories, media collections, artisan pages, and validation tests are present. |
| Phase 4: Operations Verification Panels | Implemented | Allow Area Agents, LGA Admins, State Coordinators, and Super Admins to run verification. | Filament panels, KYC queues, scoped resources, reason codes, verification actions, territory reassignment, suspensions, and audit coverage are present. |
| Phase 5: Subscriptions, Payments, Wallets | Implemented | Establish paid listing activation and auditable money movement. | Subscription plans, subscription payments, Paystack initialization/webhooks, provider event idempotency, wallets, ledger entries, payout accounts, and wallet tests are present. |
| Phase 6: Discovery And Booking Flow | Implemented | Let customers find verified subscribed artisans and complete the booking lifecycle. | Marketplace search, filtering, public profile, booking request form, guest/registered booking, secure tracker, customer/artisan booking screens, status actions, and wallet release tests are present. |
| Phase 7: Reviews, Disputes, Payouts, Reports | Implemented | Complete the MVP trust loop and operational oversight. | Verified reviews, disputes, support cases, payout requests/approval/processing, payout attempts, report snapshots, PDF rendering, Filament resources, and scoped report tests are present. |

## Known Caveat

Phase 6 in `docs/PLAN (1).md` calls for browser smoke coverage for main flows. The current repo has route and Inertia contract coverage, but no persisted Playwright, Dusk, or equivalent browser-smoke test artifact. Track this under Phase 8 hardening.

## BRS Gap Backlog

| BRS Area | Current Status | Gap To Track |
| --- | --- | --- |
| Guest booking OTP | Pending | Guest bookings can be created and tracked, but OTP-at-booking is still deferred. |
| Saved-address booking | Pending | Registered customers have address data support, but booking flow does not yet expose saved-address selection. |
| Booking payment and escrow | Pending | Payment implementation currently supports subscription checkout, not booking checkout, escrow, paid/settled booking states, or booking-payment webhooks. |
| Commission, fees, refunds, and net settlement | Partial | Wallet ledger supports immutable credits/debits, but booking-level commission, fee, refund, and net settlement policy is not fully implemented. |
| Transactional notifications | Pending | Email/SMS/WhatsApp/in-app delivery for booking, payment, review, subscription, and payout events remains unimplemented beyond existing auth/team notifications. |
| In-app chat | Pending | Controlled chat for registered customers and artisans during eligible booking states is not implemented. |
| Guest reviews and disputes | Pending | Registered customer review/dispute flows exist; guest review and dispute submission remain deferred. |
| Dedicated support inboxes | Pending | Support cases are created from disputes, but dedicated support inbox workflows are not implemented. |
| Suspicious review detection | Pending | Verified reviews and disputes exist, but suspicious review detection and routing are not implemented. |
| Review proof media and artisan responses | Pending | Reviews support rating/comment/status, but proof-of-work review media and artisan responses are not implemented. |
| Profile/payment dispute targets | Partial | Booking/review disputes exist; profile/payment dispute targets and money-changing dispute adjustments remain future work. |
| Payout automation | Partial | Manual payout review, attempts, retries, and failure records exist; provider transfer dispatch, payout webhooks/polling, and scheduled payout batches remain pending. |
| Notification delivery logs | Pending | Provider delivery status logging is not implemented. |
| Observability | Pending | Provider health, queue health, operational logs, and error monitoring are not fully implemented. |
| Data recovery | Pending | Backup, restore testing, and retention policies are not tracked in implementation. |
| Browser smoke coverage | Pending | Main marketplace, booking, tracker, customer booking, artisan booking, and operations entry points need browser-level smoke coverage. |

## Future Phase Tracker

| Phase | Status | Goal | Done When |
| --- | --- | --- | --- |
| Phase 8: MVP Hardening | Pending | Complete queued jobs, scheduled commands, notification fakes/providers, rate limits, indexes, signed media URLs, provider failure logging, mobile polish, accessibility, and browser smoke coverage. | Main flows have browser smoke coverage; hardening items are tested; all quality gates pass. |
| Phase 9: Booking Payment And Escrow | Pending | Add booking payments, booking payment purpose, paid/settled/reviewed lifecycle, commission and fee calculation, refund/adjustment entries, and Paystack-backed booking checkout/webhooks. | Customers can pay for bookings through provider checkout; webhooks update payment and booking state; wallet entries record gross, commission, fees, and net settlement. |
| Phase 10: Communication And Notifications | Pending | Implement transactional SMS/WhatsApp/email/in-app notifications, notification delivery logs, booking status notifications, subscription reminders, payout notifications, and WhatsApp webhook intake. | Required events dispatch through provider abstractions, delivery attempts are logged, and provider failures are visible to operations. |
| Phase 11: Customer Account Depth | Pending | Support OTP-at-booking, saved-address booking, guest account upgrade, guest review/dispute flows, favorites, and customer booking preferences. | Guest and registered customer flows satisfy the BRS customer journey, with tests for OTP, saved addresses, favorites, and guest upgrade paths. |
| Phase 12: Chat And Support Inbox | Pending | Add controlled booking chat for eligible registered customers/artisans, dedicated support inboxes, support assignment views, and contact/privacy safeguards. | Eligible booking states allow controlled chat, support teams can triage cases, and sensitive contact exposure is controlled. |
| Phase 13: Trust And Moderation Expansion | Pending | Add proof-of-work review media, artisan review responses, suspicious review detection, profile/payment dispute targets, and money-changing dispute adjustments. | Reviews and disputes cover media, responses, moderation signals, profile/payment targets, and audited ledger adjustments where money changes. |
| Phase 14: Payout Automation And Finance Ops | Pending | Add provider transfer dispatch, payout webhooks/polling, scheduled payout batches, bank/BVN verification where available, finance review queues, and payout exception recovery. | Payouts can run through provider-backed automation, scheduled batches, verified accounts, and finance exception queues. |
| Phase 15: Reporting, Observability, And Recovery | Pending | Fill gaps for system health, provider health, queue health, error monitoring, backup/restore tracking, retention policies, and deeper role-scoped reports. | Role dashboards include required health and reporting signals, and backup/restore/retention checks are tracked and verified. |

## Required Gates After Each Future Phase

Each future phase must pass:

| Gate | Command |
| --- | --- |
| Static analysis | `composer analyse` |
| Backend tests and coverage | `php artisan test --coverage --min=100 --compact` |
| Type checking | `npm run types:check` |
| Frontend lint | `npm run lint:check` |
| Formatting check | `npm run format:check` |
| Production build | `npm run build` |

Each future phase should also add targeted Pest tests for the changed models, actions, policies, routes, Inertia contracts, Filament actions, webhooks, and ledger behavior. Browser smoke coverage should be added for public marketplace, booking, tracker, customer booking, artisan booking, and operations panel entry points.
