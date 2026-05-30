# Guest Customer Guide

Primary surface: `/marketplace`, `/marketplace/artisans/{artisanProfile}/book`, secure booking tracker links

## Purpose

Guest Customers can make limited one-off booking requests without creating a full permanent account first. This keeps the marketplace accessible while still recording contact details, service address snapshots, and secure booking status links.

## Current Capabilities

- Browse verified approved artisans with active subscriptions on `/marketplace`.
- View public artisan profiles and active service catalog entries.
- Submit a booking request with name, phone, optional email, job notes, service address, and optional attachments.
- Receive a secure booking tracker link limited to that booking.
- Track status changes from requested through confirmed.
- Confirm completion from the tracker once the artisan marks work as finished.

## Target Workflow

1. Browse available service categories and verified subscribed artisans.
2. Choose a service and provide job details.
3. Enter name, phone number, and service address.
4. Add optional notes or attachments that help the artisan assess the job.
5. Submit booking request.
6. Use secure links to track booking status.
7. Wait for the artisan to accept, start, and finish the work.
8. Confirm completion through the secure tracker.
9. Optionally claim or upgrade to a registered customer account when that flow is available.

## Guest Rules

- The secure tracker token is the guest's access key for that specific booking.
- Do not share the tracker link with people who should not see the booking.
- Guest links must not expose unrelated customer, artisan, payment, or address records.
- Provide accurate contact and address details so the artisan can respond safely.
- Reviews should be allowed only after completed paid work.

## Escalation Path

- Booking issue: use the secure booking tracker link and keep the tracker code.
- Payment issue: keep the payment reference and phone number used for booking.
- Safety concern: contact platform support through the available support channel.

## Current Limitations

- Guest booking does not currently enforce OTP at submission time.
- Booking payment, chat, support cases, notification delivery, and guest review flow are planned later.
- Guest account claiming is separate from agent-created artisan account claiming.
