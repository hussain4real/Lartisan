# Registered Customer Guide

Primary surface: `/marketplace`, `/customer/bookings`

## Purpose

Registered Customers use Lartisan to find verified subscribed artisans, submit booking requests, track work, pay through supported checkout flows, confirm completion, leave verified reviews, chat during eligible booking states, and open booking disputes when support is needed.

## Current Capabilities

- Customer role and permission foundation exists.
- Customer profile and address data foundation exists.
- Phone verification and identity flows exist.
- Marketplace discovery and public artisan profile pages exist.
- Registered customers can create booking requests while signed in.
- Registered customers can view their own booking list and booking detail pages.
- Registered customers can confirm completion after an artisan marks a booking as finished.
- Registered customers can submit one verified review after a confirmed paid booking has released wallet credit.
- Registered customers can open disputes for their own bookings, optionally linking the booking review and attaching private evidence.

## Target Workflow

1. Register or sign in.
2. Verify phone number.
3. Save default service address.
4. Browse service categories and verified subscribed artisans.
5. Submit a booking request with schedule, location, and job notes.
6. Track artisan response and job status.
7. Confirm job completion after the artisan finishes work.
8. Pay through the platform when checkout is presented.
9. Leave a verified review after completed paid work.
10. Open a dispute from the booking detail page if service delivery, review, safety, or payment context needs operations review.

## Customer Rules

- Provide accurate contact and address details.
- Keep service communication inside approved app channels where available.
- Pay through the platform to preserve protection, receipts, and review eligibility.
- Leave reviews only for real completed jobs.
- Submit only one review per eligible booking.
- Use disputes for genuine booking or review issues and include clear evidence when available.
- A registered customer can see only bookings tied to their own user account.

## Escalation Path

- Booking problem: use the booking detail page and keep the tracker code.
- Payment issue: escalate with payment reference and booking context.
- Artisan misconduct or safety issue: escalate to LGA operations or platform support.

## Process Runbooks

- [Marketplace discovery and listing visibility](../processes/marketplace-discovery-and-listing-visibility.md)
- [Booking trust lifecycle](../processes/booking-trust-lifecycle.md)
- [Communications and support operations](../processes/communications-and-support-operations.md)

## Current Limitations

- Booking chat is available only for registered bookings while they are requested, accepted, paid, escrowed, or in progress; closed booking chats remain readable but not writable.
- Support help continues through booking disputes and the operations support inbox rather than a separate customer-facing support center.
