# Artisan Guide

Primary surface: `/{team}/artisan`

## Purpose

Artisans use Lartisan to prepare a verified public business listing, manage services, submit KYC, activate a paid listing subscription, manage booking requests, and view wallet information.

## Current Workspace Pages

| Page | Purpose |
| --- | --- |
| Dashboard | View profile status, service count, KYC count, and recent services. |
| Artisan profile | Maintain public business information and portfolio media. |
| Services | Add catalog entries, service category, description, price, currency, and status. |
| KYC | Upload verification evidence and view latest submission status. |
| Subscription | Select a listing plan and start Paystack checkout. |
| Wallet | View wallet balances, ledger entries, and payout account records. |
| Bookings | View customer booking requests and move work through the booking lifecycle. |
| Onboarding | Complete assisted onboarding details. |
| Phone verification | Verify account phone number with OTP. |

## Standard Workflow

1. Sign in and switch to the artisan business team.
2. Open the artisan dashboard.
3. Complete public profile details and portfolio media.
4. Add services to the catalog.
5. Submit KYC evidence.
6. Wait for operations review and field verification.
7. After approval, choose a subscription plan.
8. Pay through Paystack checkout.
9. Confirm subscription status and listing visibility.
10. Review incoming booking requests.
11. Accept or reject requested bookings.
12. Start accepted work when the job begins.
13. Mark in-progress work as finished when ready for customer confirmation.
14. Review wallet and ledger entries after confirmed booking completion.

## Booking Workflow

| Status | Artisan action | Result |
| --- | --- | --- |
| Requested | Accept | Booking moves to accepted and can be started. |
| Requested | Reject | Booking closes as rejected. |
| Accepted | Start | Booking moves to in progress. |
| In progress | Finish | Booking waits for customer confirmation. |
| Finished | None | Customer confirms completion through customer screen or secure tracker. |
| Confirmed | None | Wallet release is posted as an immutable booking credit ledger entry when a quote exists. |

## Discovery Rules

- A public listing appears in marketplace discovery only when verification is approved, subscription is active, and the listing is public.
- Marketplace search uses service category, geography, and availability to rank artisans.
- Vacation availability prevents new marketplace discovery in the current implementation.

## KYC Evidence

Current KYC upload collections:

- Government ID.
- Self portrait.
- Address evidence.
- Business registration.

## Subscription Rules

- An artisan business can have one active paid listing subscription at a time.
- Successful Paystack payment activates the subscription.
- Public listing visibility requires both approved verification and active subscription.
- Failed or abandoned payments do not activate a listing.

## Wallet Rules

- Wallet ledger entries are append-only.
- Balance corrections must be posted as adjustment entries.
- Do not expect wallet records to be manually edited.
- Confirmed bookings post booking-credit ledger entries once; repeated release attempts return the existing ledger entry.
- Payout processing is planned later.

## Current Limitations

- Booking payment collection, chat, dispute handling, notifications, and reviews are planned later.
- Payout requests and payout processing are planned later.
