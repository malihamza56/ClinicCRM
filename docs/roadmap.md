# Roadmap

Ordered by value for a real clinic. Each item names the existing hook that makes it cheap.

## Phase A - make it safer and friendlier
1. **Password reset by email** - needs a mail provider; add a `password_resets` table (hashed token + expiry) and two routes.
2. **Two-factor authentication** (TOTP) for admin and accountant roles.
3. **Notifications** - SMS/WhatsApp/email reminders for appointments and follow-ups. Add a `notifications` queue table and a CLI worker (`bin/worker.php`); follow-ups already store dates and status. Show "sent" only when a provider confirms delivery.
4. **Appointment calendar view** (week/day per doctor) on top of `AppointmentService::slots()`.

## Phase B - multi-clinic and portal
5. **Multi-clinic scoping** - `clinic_id` exists on users, patients, services, invoices, settings. Add it to every Model query (or a global scope helper), add a clinic switcher for super-admins, and extend the permission tests.
6. **Patient portal** - activate the `patient` role and `patients.user_id`; routes limited to own appointments, prescriptions and invoices with a strict ownership policy (the care-relationship policy in `AccessPolicy` is the template).
7. **Online booking** using the existing slot engine with captcha and rate limits.

## Phase C - clinical and financial depth
8. Lab/imaging orders and results; medicine catalogue with interaction warnings from a licensed data source.
9. Insurance/panel billing, credit notes, multi-currency, tax rules per service, payment gateway integration.
10. PDF generation for prescriptions/invoices, letterhead and logo upload.
11. Inventory and pharmacy stock.

## Phase D - platform
12. Replace static helpers with a small DI container if the team grows; adopt PHPUnit/CI (the test bodies port directly).
13. Background jobs (backups, purge of old login attempts), structured logging, health endpoint.
14. Hosted/SaaS deployment: tenant provisioning, per-tenant backups, billing, monitoring, a compliance review for the target jurisdictions.
