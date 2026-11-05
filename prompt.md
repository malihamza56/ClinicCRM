# Master Build Prompt — Clinic CRM

## Role and mission

Act as a senior software architect, PHP engineer, database designer, UI/UX implementer, QA engineer, and technical writer. Build a complete, professional, maintainable **Clinic CRM** web application suitable for both a university BSCS project and a portfolio demonstration.

Do not stop at mockups, pseudocode, or a plan. Implement the working application, test it, fix discovered defects, document it, and package the finished project as a ZIP file.

The user will provide:
1. UI design token/reference files.
2. A login-page reference image/design.
3. The chosen color palette.

Treat those uploaded assets as the visual source of truth. Inspect them before implementing the UI. Follow their spacing, colors, typography, borders, and component style where practical. Do not invent a flashy redesign or override the supplied design system. If an asset is missing or ambiguous, use restrained, accessible defaults and document the assumption.

## Product decisions — do not change without a clear technical reason

- Product name: **Clinic CRM**.
- Product type: web-based clinic management and customer/patient relationship system.
- Clinic type: **multi-specialty clinic**.
- Initial deployment: **one clinic**, with a clean architecture that can be extended to multiple clinics later.
- Patient portal: **not part of the first MVP**; plan the data model so it can be added in a later phase without a major rewrite.
- Billing: consultation fees + clinic services + full and partial payments.
- Technology: PHP 8+ (prefer a currently supported stable version available in the target environment), MySQL/MariaDB, HTML5, CSS3, JavaScript, Bootstrap only if it fits the supplied design tokens, and Chart.js for charts if useful.
- Local environment: Windows + XAMPP, VS Code, phpMyAdmin; application should also be deployable to a conventional PHP/MySQL hosting environment.
- Architecture: modular, MVC-style, beginner-readable PHP. Use Composer/autoloading if appropriate and available; do not introduce a large framework without first explaining the trade-off. Prefer a simple, explicit architecture that is easy for a student to explain in a viva.
- Development standard: readable, formatted, well-named code, small focused functions/classes, useful comments explaining *why* where needed, no unnecessary cleverness, no copy-paste duplication, no dead code, and no fake functionality.
- Final project directory inside the ZIP must be exactly named **Clinic CRM**.

## Main goal

Create a real, coherent application, not a collection of unrelated CRUD pages. Its workflows must connect correctly: patient → appointment → visit/consultation → prescription/follow-up → invoice → payments → reports.

## Required user roles and access

Use one centralized login page for staff accounts. Authentication must resolve the account's role and route the user to the correct dashboard. Do not rely on hiding navigation links alone; enforce every permission on the server.

### Roles

1. **Clinic Admin**
   - Manage staff accounts and their activation status.
   - Manage doctors, specialties, clinic services, consultation fees, and clinic settings.
   - View operational dashboards and permitted reports.
   - Manage patients, appointments, billing oversight, and follow-ups.
   - Review audit logs.
   - Administrative access must not permit silent, untraceable alteration of clinical or financial history.

2. **Doctor**
   - View their assigned appointments and queue.
   - Access patient records only where the clinic's authorization policy allows it.
   - Record consultations, symptoms, vitals, diagnosis, treatment notes, prescriptions, and follow-ups.
   - View relevant patient history.
   - Must not manage staff accounts or change payment records by default.

3. **Receptionist**
   - Register/search patients and update permitted demographic/contact details.
   - Schedule, reschedule, cancel appointments, and manage the waiting queue.
   - Handle walk-in patients.
   - View only the patient details necessary for front-desk operations.
   - May create an invoice or record a payment only if the final permission matrix explicitly allows it; never grant broad financial-report access by default.

4. **Accountant**
   - Manage invoices, invoice items, payments, balances, refunds/adjustments according to policy, and financial reports.
   - Access only the patient details necessary for billing.
   - Must not access full medical history, create diagnoses, or edit prescriptions.

5. **Patient**
   - Patient portal is a future phase and must not be presented as an active MVP feature.
   - Keep the design/database extensible for future patient accounts, with strict record ownership so a patient can only see their own appointments, prescriptions, bills, and permitted records.

Use a normalized role/permission model or a clearly documented RBAC approach. Avoid scattering hard-coded role strings across the codebase. If a simple role column is chosen for the MVP, centralize role constants and permission checks, and document the path to more granular permissions.

## Core functional requirements

### 1. Authentication and accounts
- Login, logout, session validation, account activation/deactivation.
- Secure password hashing with `password_hash()` and verification with `password_verify()`.
- Regenerate session ID after login; configure session cookie flags appropriately for the environment.
- Rate-limit or add a reasonable progressive delay/lockout policy for repeated failed logins without creating an easy denial-of-service vector.
- Generic login errors that do not reveal whether an email exists.
- Server-side authorization on every protected route and sensitive action.
- Do not ship a default production admin password. If seed/demo credentials are provided, mark them development-only and require a password change.
- Password reset can be deferred if email delivery is not configured; clearly document the limitation rather than faking it.

### 2. Patient management
- Create, view, search, update, and archive patient records.
- Generate a unique, human-readable patient identifier.
- Store only necessary information: name, date of birth or age derived from DOB, contact details, address if required, emergency contact if required, registration date, and clinically appropriate optional demographics.
- Validate required fields, email/phone formats where applicable, date ranges, duplicates, and maximum lengths.
- Do not use a patient's name as a unique key.
- Preserve relationships and historical records when archiving a patient.
- Search by patient ID, name, and phone with safe parameterized queries.

### 3. Specialty and doctor management
- Manage specialties and doctor profiles.
- Link each doctor profile to a user login account.
- Store specialization, contact details, consultation fee, active status, and availability/schedule as appropriate.
- Prevent conflicting or invalid schedule entries.
- Keep authentication account data separate from professional profile data.

### 4. Appointment scheduling
- Create, view, reschedule, cancel, and update appointment status.
- Select a registered patient and an active doctor/specialty.
- Validate dates, clinic hours, doctor availability, and overlapping appointments.
- Support walk-in patients without creating duplicate patient records.
- Support queue tracking.
- Keep appointment status separate from visit status and invoice/payment status.
- Define and validate status transitions; do not permit arbitrary invalid transitions.
- Store cancellation reason where appropriate.
- Handle double-booking attempts safely, including concurrent requests as far as the database design permits.

Suggested appointment statuses: `Scheduled`, `Confirmed`, `Waiting`, `In Progress`, `Completed`, `Cancelled`, `No Show`. Document which transitions are valid.

### 5. Visits and medical records
- Link every consultation/visit to a patient, treating doctor, and appointment when applicable.
- Support walk-in visits that may not have a pre-existing appointment.
- Store complaint/symptoms, relevant vitals, diagnosis, treatment plan, notes, and follow-up recommendation.
- Validate numeric vital fields and plausible ranges without pretending to provide medical diagnosis.
- Restrict clinical record access by role and care relationship.
- Preserve a history of amendments to completed records; do not silently overwrite finalized clinical data.
- Do not hard-delete finalized medical records through normal UI workflows.
- Use dummy data only in development and screenshots.

### 6. Prescriptions
- Create prescriptions associated with a visit and patient.
- Support multiple prescription items with medicine name, dosage, frequency, duration, and instructions.
- Validate required fields and reasonable text lengths.
- Provide a clean print-friendly prescription view.
- Do not claim that the application provides medical advice or verifies drug safety.

### 7. Clinic services, invoices, and payments
- Configure consultation fees and additional clinic services.
- Create invoices with one or more line items.
- Store amounts using a safe decimal strategy suitable for currency; never use floating-point arithmetic for financial calculations.
- Compute subtotal, discounts/taxes only if configured, total, paid amount, and outstanding balance consistently on the server.
- Support unpaid, partial, and paid invoices.
- Support multiple payments per invoice.
- Record payment date, amount, method, reference/receipt number where applicable, and the staff account that recorded it.
- Prevent negative amounts and invalid overpayments unless a documented policy supports them.
- Use database transactions for payment posting and balance-related updates.
- Do not delete payment history silently. Model refunds/voids/adjustments as traceable records.
- Generate a printable invoice/receipt.
- Financial reports must be calculated from persisted records, not hard-coded sample numbers.
- Make currency configurable; default to PKR (Rs.) for the demo unless the user requests otherwise.

### 8. Follow-ups
- Record follow-up date, notes, related patient, visit, and doctor.
- List upcoming, due, completed, and overdue follow-ups.
- Automated SMS/WhatsApp/email notifications are future enhancements unless fully configured; do not display fake “sent” statuses.

### 9. Dashboards and analytics
- Role-specific dashboards with only permitted data.
- Admin: patient counts, today's appointments, active doctors, new registrations, outstanding balances, and revenue summaries.
- Doctor: assigned appointments, queue, and follow-ups.
- Receptionist: appointments, queue, and patient-registration shortcuts.
- Accountant: invoices, payments, outstanding balances, and financial summaries.
- Use Chart.js only where a chart communicates useful information. Ensure charts have labels, legends/tooltips where appropriate, responsive layouts, and empty states.
- All dashboard numbers must be derived from database queries.
- Include date filters where relevant and make the selected date range clear.

### 10. Search, filtering, exports, and audit
- Provide practical search and filters for patients, appointments, doctors, invoices, and payments.
- Add CSV export only where useful and authorized; mitigate spreadsheet formula injection in exported user-controlled values.
- Add audit logs for important security, clinical, and financial events (e.g. account changes, visit amendments, invoice/payment/refund operations). Avoid logging passwords, session tokens, or unnecessary sensitive data.
- If backup tooling is included, document how to create and restore a database backup and test the process.

## UX/UI requirements

- The user will upload UI design tokens, a login-page reference, and a color palette. Inspect and follow those assets.
- Use a simple, professional, calm healthcare UI. Do not make the UI “over-effective,” overly animated, excessively colorful, or crowded.
- Keep typography readable, contrast accessible, spacing consistent, forms clear, and table layouts practical.
- Use only subtle transitions (e.g. hover/focus states and small panel transitions). Respect `prefers-reduced-motion`.
- Make login, dashboards, patient tables, forms, appointment views, prescription print view, billing screens, and reports visually consistent.
- Use responsive layouts for desktop/tablet/mobile.
- Include loading, success, validation-error, permission-denied, empty, and server-error states.
- Confirm destructive actions and explain their consequences.
- Use semantic HTML, visible focus states, accessible labels, keyboard navigation, and appropriate contrast.
- Do not invent logos or branding that conflict with supplied assets.
- Avoid unnecessary animation libraries, heavy front-end frameworks, excessive gradients, glassmorphism, and decorative charts unless the supplied tokens explicitly require them.

## Technical architecture and code quality

- Use a clear MVC-style structure and explain request flow.
- Keep database access out of view templates.
- Use PDO prepared statements for database operations.
- Centralize configuration, routing, authentication, authorization, validation, error handling, and reusable helpers.
- Keep controllers thin; put business rules in appropriate service/model layers.
- Use transactions for multi-step operations that must stay consistent.
- Avoid giant files/classes, global mutable state, duplicated validation, unexplained magic values, and deeply nested conditional code.
- Use strict, predictable naming conventions and consistent formatting.
- Add comments for intent, business rules, security-sensitive choices, and non-obvious logic—not comments that merely repeat the code.
- Include a `.env.example`; never commit actual secrets. If dotenv is not installed, use a clearly documented config-loading approach and do not pretend PHP automatically reads `.env`.
- Include `.gitignore` for secrets, logs, temporary files, and generated dependencies as appropriate.
- Use migrations or a documented repeatable schema process.
- Include seed/demo data that is clearly fictional and safe.
- Ensure no credentials, real patient data, API keys, or secrets are present in Git-tracked files.
- Avoid claiming that the code is “bug-free.” Instead, demonstrate testing performed, report results honestly, and list remaining limitations.

## Suggested project structure

Adapt this only when there is a clear reason, and document any deviations.

```text
Clinic CRM/
├── app/
│   ├── Config/
│   ├── Controllers/
│   ├── Models/
│   ├── Services/
│   ├── Views/
│   ├── Middleware/
│   └── Helpers/
├── bootstrap/
├── config/
├── database/
│   ├── migrations/
│   ├── schema.sql
│   └── seed.sql
├── docs/
│   ├── architecture.md
│   ├── workflow.md
│   ├── setup.md
│   ├── requirements.md
│   ├── database.md
│   ├── security.md
│   ├── testing.md
│   └── user-manual.md
├── public/
│   ├── index.php
│   └── assets/
│       ├── css/
│       ├── js/
│       ├── images/
│       └── vendor/
├── routes/
├── storage/
│   ├── logs/
│   └── backups/
├── tests/
├── .env.example
├── .gitignore
├── composer.json
├── README.md
└── prompt.md
```

Do not expose `app`, `config`, `database`, `storage`, `.env`, or other private files directly through the public web root. For XAMPP, document a safe virtual-host/public-root setup or an appropriate local-only configuration. Do not recommend deploying the entire project directory as the web root.

## Required documentation — all must be created and kept accurate

### `README.md`
Must include:
- Project title, purpose, key features, scope, roles, and screenshots section.
- Technology stack and architecture summary.
- Prerequisites and quick-start links.
- Database setup, demo account instructions, and how to run tests.
- Project folder structure overview.
- Security and privacy notes.
- Known limitations and future roadmap.
- Current version and honest testing status.

### `architecture.md` (inside `docs/`)
Must explain:
- System architecture and why it was selected.
- MVC responsibilities and request lifecycle.
- Folder/module responsibilities.
- Authentication, RBAC, and authorization flow.
- Database access patterns and transaction boundaries.
- Security boundaries and future extension points.

### `workflow.md` (inside `docs/`)
Must explain:
- Role-specific workflows.
- Patient registration, walk-in, appointments, queue, consultation, prescription, follow-up, invoice, and payment workflows.
- Status transitions and invalid transitions.
- Error/exception flows.
- Use Mermaid diagrams where they improve understanding; ensure syntax is valid.

### `setup.md` (inside `docs/`)
Must provide exact, beginner-friendly Windows/XAMPP setup steps:
- Required PHP version/extensions and MySQL/MariaDB.
- Project placement and safe web-root/virtual-host setup.
- Composer setup if required.
- Copy `.env.example` to `.env` and configure values.
- Create database and import schema/migrations/seed data.
- Start Apache and MySQL.
- Run the application and tests.
- Demo credentials, if supplied, and instructions to change them.
- Troubleshooting for common PHP/MySQL/configuration errors.
- Production deployment cautions.

Also create `docs/requirements.md`, `docs/database.md`, `docs/security.md`, `docs/testing.md`, and `docs/user-manual.md` with accurate content.

## Validation and testing — mandatory, not optional

Do not call the project finished merely because pages load. Build a test plan before implementation, then run the checks that the environment supports. If a tool or dependency is unavailable, say so and provide the exact command the user can run; never fabricate a passing result.

### Static/code checks
- PHP syntax check all PHP files (`php -l`).
- Run a formatter/linter/static analysis tool if configured and available.
- Check for missing includes/classes/routes and undefined variables where tooling permits.
- Verify no secrets or production credentials are committed.
- Check all internal links/forms/routes and asset paths.

### Database checks
- Import schema into a clean database.
- Verify primary/foreign keys, unique constraints, indexes, and deletion behavior.
- Test seed script repeatability or document that it should only be run once.
- Test transaction rollback behavior for failed invoice/payment operations.
- Verify all reports query real database data.

### Functional tests
Test at minimum:
1. Valid and invalid login.
2. Inactive account login rejection.
3. Logout and session invalidation.
4. Direct URL access by unauthorized roles.
5. Patient create/search/update/archive and duplicate prevention.
6. Doctor/specialty CRUD and account/profile linking.
7. Appointment creation, rescheduling, cancellation, walk-ins, unavailable doctor, overlapping booking, and status transitions.
8. Consultation save, validation failures, record ownership/access boundaries, and amendment history.
9. Prescription with multiple items and print layout.
10. Invoice totals, multiple line items, partial payment, final payment, invalid amounts, overpayment handling, refund/adjustment flow, and balance consistency.
11. Follow-up creation and date filtering.
12. Dashboard counts and financial reports against known seeded records.
13. Input validation, SQL injection attempts, XSS payloads, CSRF protection, and authorization bypass attempts.
14. Empty database, missing fields, invalid dates, and database connection failure behavior.
15. Responsive layout and keyboard/focus basics.

Use automated tests (for example PHPUnit if appropriate) for business rules and integration points. Also provide a repeatable manual QA checklist. Tests must use disposable test data and never real patient data.

### Bug-fixing loop
For each defect found:
1. Reproduce it.
2. Identify root cause.
3. Fix the underlying issue, not only the visible symptom.
4. Add or update a regression test.
5. Rerun the relevant test and the broader suite.
6. Update the documentation if behavior changed.

Run the complete available test suite after the last code change. Do not claim zero bugs; provide a transparent test report and known-issues list.

## Implementation process — work in controlled milestones

1. Inspect the provided design-token, palette, and login reference assets.
2. Convert requirements into a traceable feature checklist and role-permission matrix.
3. Finalize data model/ERD, constraints, and status-transition rules.
4. Create architecture and setup skeleton before implementing business modules.
5. Implement configuration, routing, database access, validation, error handling, authentication, and RBAC.
6. Implement modules in this order:
   - User/authentication
   - Specialties/doctors
   - Patients
   - Appointments and queue
   - Visits/medical records
   - Prescriptions
   - Services/invoices/payments
   - Follow-ups
   - Dashboards/reports
   - Audit logging and permitted exports
7. Add UI polish only after workflows are functional; follow supplied tokens.
8. Write automated tests alongside features; do not postpone all testing to the end.
9. Perform end-to-end role and workflow testing.
10. Fix bugs and rerun checks.
11. Finish all documentation, setup instructions, and known limitations.
12. Package the complete project.

At each milestone, keep a `docs/implementation-status.md` checklist updated so work can resume after interruption. Do not ask the user to approve every trivial coding choice; make reasonable documented decisions. Ask a concise question only if a missing decision blocks safe or correct implementation.

## Definition of done

The project is complete only when:
- The documented MVP workflows are implemented and connected.
- Role permissions are enforced server-side.
- The database schema imports cleanly on a fresh database.
- Validation, error handling, and security protections are present.
- Functional and regression tests have been run and results documented honestly.
- `README.md`, `docs/architecture.md`, `docs/workflow.md`, `docs/setup.md`, and the other required documents exist and match the actual code.
- The app can be installed by following the setup guide on a clean local environment.
- There are no known critical/high-severity defects left unaddressed. Any remaining defects or untested areas are explicitly listed.
- UI follows the uploaded design tokens and palette without excessive effects.
- Code is readable and suitable for a student to explain in a university presentation/viva.
- No real patient data or secrets are included.
- The final archive is named clearly and contains one top-level folder named `Clinic CRM`.

## Final deliverables

Return:
1. The complete source project.
2. `prompt.md` containing this master prompt.
3. `README.md`.
4. `docs/architecture.md`.
5. `docs/workflow.md`.
6. `docs/setup.md`.
7. Requirements, database, security, testing, and user-manual documentation.
8. Database schema/migrations and fictional seed data.
9. Automated tests and a manual QA checklist.
10. A test report with exact checks run, pass/fail/blocked status, and known issues.
11. A ZIP archive containing the entire project under the top-level directory **`Clinic CRM/`**.

Before returning, inspect the archive contents, confirm all required files are present, and verify that the ZIP opens. Provide the exact path/link to the generated ZIP. If execution tools cannot run the PHP/MySQL app in the current environment, be transparent: still run every available check, mark environment-dependent checks as blocked, and give the user exact local commands to finish verification. Never claim the application is fully tested or bug-free when it was not actually run.
