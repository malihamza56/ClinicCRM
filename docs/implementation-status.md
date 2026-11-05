# Implementation status

Version 1.0.0. This is the honest "what exists" list, in the order the modules were built. See [requirements.md](requirements.md) for the line-by-line traceability.

## Milestones

| # | Milestone | Status |
|---|---|---|
| 1 | Inspect supplied assets (dashboard reference image) | ✅ palette, layout and component style extracted; login/tokens files were not supplied (assumption recorded) |
| 2 | Requirements checklist + role-permission matrix | ✅ requirements.md, security.md |
| 3 | Data model, constraints, status rules | ✅ schema.sql (23 tables), database.md, workflow.md |
| 4 | Architecture and setup skeleton | ✅ bootstrap, config, router, middleware, README/setup |
| 5 | Config, routing, DB access, validation, errors, auth, RBAC | ✅ |
| 6 | Modules: users → specialties/doctors → patients → appointments/queue → visits/prescriptions → follow-ups → services/invoices/payments → dashboards/reports → audit/exports | ✅ all built; each with tests |
| 7 | UI polish against the reference | ✅ design tokens, KPI cards, charts, responsive, print views; screenshots reviewed |
| 8 | Tests written alongside features | ✅ 138 automated tests + lint + browser checks |
| 9 | End-to-end role and workflow testing | ✅ full journey test, per-role link crawl |
| 10 | Bug-fix loop | ✅ 10 defects found and fixed (listed in test-report.md) |
| 11 | Documentation | ✅ |
| 12 | Packaging | ✅ ZIP with top-level `Clinic CRM/` |

## What is implemented (by module)

* **Authentication:** login/logout, forced password change, throttling, session hardening, admin-issued temporary passwords.
* **Users/RBAC:** 4 staff roles + reserved patient role; 35 permissions; route-level and record-level checks.
* **Patients:** CRUD-minus-delete (archive/restore), duplicate guard, search, CSV export, profile with appointments/clinical history/invoices/follow-ups according to role.
* **Doctors/specialties/services/settings:** full management screens with validation.
* **Scheduling:** slot engine, booking, reschedule, cancel, check-in, no-show, walk-in, queue.
* **Consultations:** draft/finalize/amend, vitals validation, multi-line prescriptions, printable prescription, per-view audit.
* **Follow-ups:** buckets, date filter, create/complete/cancel.
* **Billing:** invoices, payments, refunds, adjustments, voids, receipts, printable invoice, live estimate.
* **Dashboards/reports:** 4 role dashboards, financial and operational reports, Chart.js charts.
* **Operations:** audit log viewer, CSV exports, backup/restore, installer, admin creation, demo seeder.
* **Front end:** design tokens, responsive shell, confirm dialog, empty/error/loading states, SVG illustrations, vendored fonts/icons/Chart.js.

## Deliberately not implemented
Patient portal · password reset by email · SMS/WhatsApp/email notifications · multi-clinic scoping in queries · two-factor authentication · invoice editing (void and re-issue instead) · calendar/week view of appointments · file uploads · inventory/lab modules · PDF generation (browser print is used).

## Defects found during development (summary)
See [test-report.md](test-report.md#defects-found-and-fixed). All were fixed and are covered by regression tests where practical.

## File inventory (approximate)
| Area | Files | Lines |
|---|---|---|
| `app/Core` | 19 | 1.3k |
| `app/Models` | 15 | 1.5k |
| `app/Services` | 15 | 2.1k |
| `app/Controllers` | 16 | 1.4k |
| `app/Views` | 53 | 1.6k |
| CSS / JS (own) | 4 | 0.7k |
| Tests | 15 | 2.4k |
