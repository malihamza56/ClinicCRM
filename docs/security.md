# Security and privacy

This is a medical-adjacent system holding personal and financial data. This document states what is protected, **how**, **how it was tested**, and what is **not** covered. It is an honest engineering review, not a certification: the system has **not** had an independent penetration test or a compliance (HIPAA/GDPR/PDPA) audit.

## 1. Role-permission matrix

Permissions are rows in `permissions`; `role_permissions` maps them to roles; `Auth::can()` reads them from the database on each request. A test pins this exact matrix, and another asserts that code constants, routes and database agree.

| Permission | Admin | Doctor | Receptionist | Accountant |
|---|:-:|:-:|:-:|:-:|
| `dashboard.view` | ✔ | ✔ | ✔ | ✔ |
| `users.manage`, `specialties.manage`, `doctors.manage`, `services.manage`, `settings.manage`, `audit.view` | ✔ | | | |
| `doctors.view` | ✔ | | ✔ | |
| `patients.view` (full demographics) | ✔ | ✔ ¹ | ✔ | |
| `patients.view_billing` (name, code, phone only) | | | | ✔ |
| `patients.create`, `patients.update` | ✔ | | ✔ | |
| `patients.archive` | ✔ | | | |
| `appointments.view` | ✔ | ✔ ¹ | ✔ | |
| `appointments.manage` (book, reschedule, cancel, check-in, walk-in) | ✔ | | ✔ | |
| `appointments.consult` (start consultation) | | ✔ ¹ | | |
| `queue.view` | ✔ | ✔ ¹ | ✔ | |
| `visits.view`, `visits.write`, `visits.amend` (clinical records, prescriptions) | | ✔ ² | | |
| `followups.view`, `followups.update` | ✔ | ✔ ¹ | ✔ | |
| `followups.create` | | ✔ | | |
| `invoices.view` | ✔ | | ✔ | ✔ |
| `invoices.create` | | | ✔ | ✔ |
| `invoices.void` | | | | ✔ |
| `payments.view` (ledger) | ✔ | | | ✔ |
| `payments.record` | | | ✔ | ✔ |
| `payments.refund`, `payments.adjust` | | | | ✔ |
| `reports.financial`, `exports.financial` | ✔ | | | ✔ |
| `reports.operational`, `exports.patients`, `exports.appointments` | ✔ | | | |

¹ Restricted to the doctor's **own** appointments/queue/follow-ups and to patients with a **care relationship** (a non-cancelled appointment or a recorded visit with that doctor).
² Only for patients with a care relationship; **editing/amending only by the treating doctor**.

Deliberate choices:

* **Admin has no clinical access** (data minimisation) and **cannot record or refund money** — "oversight, not silent alteration".
* Receptionists can create invoices and take payments (front-desk reality) but cannot refund, void, see the ledger or reports.
* The `patient` role exists but cannot sign in to the staff application.

## 2. Threats considered and controls

| Threat | Control | Verified by |
|---|---|---|
| Unauthenticated access | Auth middleware on every non-public route; only `/login` and `/` are public | HTTP test: 12 URLs redirect to login |
| Missing/forged authorization | Server-side permission check; **deny-by-default** for routes without a rule; hidden menu items are cosmetic only | route-table test; HTTP tests for 4 roles × many URLs (403) |
| Horizontal access (another doctor's patient/visit) | `AccessPolicy` care-relationship and ownership rules, also applied to search/lookup/lists | HTTP tests (patient page, appointment, search leakage, visit edit POST) |
| CSRF | Per-session random token on every POST (`hash_equals`), rotated at login; `SameSite=Lax` cookie | forged/missing/foreign-session tokens → 419; no record created |
| Session fixation / theft | `session_regenerate_id(true)` at login; strict mode, cookies only, `HttpOnly`, `Secure` on HTTPS; idle timeout; account re-read from DB on every request (deactivation is immediate) | HTTP tests (cookie changes at login, deactivated user cut off); idle timeout **not** automated |
| Brute force / credential stuffing | 5 failures per **email+IP** in 15 min locks that pair (an attacker cannot lock out the real user from another address); IP-wide cap; generic error; dummy hash keeps timing equal; passwords ≥ 10 chars with letters and numbers | integration + HTTP tests |
| Account enumeration | Same message for wrong password, unknown email, inactive account, patient role | integration test |
| SQL injection | PDO prepared statements, native (non-emulated) prepares; `LIKE` wildcards escaped; enums/whitelists for filters; no SQL built from input | injection payloads against 8 search/filter endpoints |
| XSS (stored/reflected) | All output through `e()`; lint fails on bare echoes; CSP forbids inline and third-party scripts; no `innerHTML` with user data in JS (text set via `textContent`) | HTTP test with `<img onerror>` / `"><script>` payloads, lint |
| Clickjacking / sniffing | `X-Frame-Options: DENY`, CSP `frame-ancestors 'none'`, `nosniff`, `Referrer-Policy`, `Permissions-Policy` | HTTP test |
| Open redirect | After login only in-app paths remembered by the server are followed (never a user-supplied URL) | code path + test |
| CSV/formula injection | Cells starting `= + - @ TAB CR` are prefixed with `'`; UTF-8 BOM | unit + HTTP test |
| Double spending / overpayment / race conditions | Invoice row lock + transaction; ledger recalculation; overpayment refused | billing tests + parallel booking test |
| Tampering with history | Triggers make payments, audit log, amendments append-only; invoices/finalized visits undeletable | database tests (UPDATE/DELETE blocked) |
| Price tampering | Server takes prices from the DB; browser sends IDs/quantities only | billing tests |
| Mass assignment | Controllers pass only validated, whitelisted fields to services | code review |
| Information leakage | Friendly 403/404/419/503/500 pages; details only in `storage/logs` (never passwords); `APP_DEBUG=false` hides traces | HTTP tests (DB outage page leaks nothing; log checked for the DB password) |
| Secrets in source | `.env` git-ignored, `.env.example` only; `.htaccess` denies `.env`, SQL and log files; no default admin password | grep review (below) |
| Path/file exposure | Only `public/` is a web root; each private folder has a deny `.htaccess` | documented; **Apache behaviour untested** |

### Password handling
`password_hash(PASSWORD_DEFAULT)` (bcrypt today), rehash-on-login when the algorithm improves, 72-byte maximum enforced (bcrypt limit), temporary passwords are random (12 chars + digit, no look-alikes), shown **once** on screen to the admin and never logged or stored in plain text. Temporary and demo passwords force a change at first sign-in.

### Audit trail
Logged: sign-in/out, failures (IP only), password changes, user/doctor/specialty/service/settings changes, patient create/update/archive, appointment create/reschedule/status/walk-in, visit start/finalize/amend/**view of clinical record**/prescription print, follow-up changes, invoice create/void, every payment/refund/adjustment, every export. `audit_logs` stores user, time, IP, a short summary and a sanitised detail blob (keys containing *password/token/hash/secret* are stripped). The log is visible only to admins.

## 3. Data protection practices
* **Minimum necessary:** clinical data only for doctors; billing roles see name/code/phone only; admin sees demographics but not clinical content.
* **Retention:** records are archived, never hard-deleted; the operator must define retention/erasure policy and procedures (not implemented).
* **Backups** contain everything; store encrypted and access-controlled.
* **No real patient data** is in the repository; all seed data is fictional.

## 4. Production hardening checklist
- [ ] HTTPS everywhere; `SESSION_COOKIE_SECURE=true`; HSTS (sent automatically over HTTPS)
- [ ] `APP_ENV=production`, `APP_DEBUG=false`
- [ ] Web root = `public/` only
- [ ] Dedicated least-privilege DB user (SELECT/INSERT/UPDATE/DELETE); root not used by the app
- [ ] Installed without demo data; first admin from `bin/create-admin.php`; strong passwords
- [ ] `storage/logs` writable only by the web server; log rotation; monitor repeated login failures
- [ ] Regular, tested, encrypted, off-site backups
- [ ] PHP `expose_php=Off`, `display_errors=Off`, OS and PHP patched
- [ ] Rate limiting / WAF at the reverse proxy for the login URL if internet-facing
- [ ] Two-factor authentication (not implemented — see roadmap) for admin and accountant accounts
- [ ] Review of local privacy law (consent, retention, breach notification)

## 5. Known residual risks and gaps
1. No two-factor authentication; no password-reset flow (admin-issued temporary passwords instead).
2. `style-src 'unsafe-inline'` remains in the CSP because templates use a few inline `style` attributes; scripts are strict.
3. Login throttling is per email+IP and does not stop a large distributed attack.
4. Sessions use PHP file storage (single-server assumption); idle timeout not automatically tested.
5. Admins can create accounts and reset passwords; a malicious admin could therefore create a doctor account. The audit log records it, but there is no second-person approval.
6. A doctor with a care relationship can see that patient's whole history including other doctors' visits (policy choice; configurable in `AccessPolicy`).
7. Patient edits are audited as "updated patient details" without before/after values (privacy vs traceability trade-off).
8. No malware scanning (there are no file uploads), no field-level encryption at rest — rely on disk/database encryption.
9. Apache `.htaccess` protections and the XAMPP setup are unverified on Windows.

## 6. Secret scan performed before packaging
The repository was searched for committed secrets (`password`, `secret`, `token`, API keys, private keys). Findings: only the documented fictional demo password `ChangeMe!2026` (seed + docs, development only) and the test fixture password in `tests/Support/Fixtures.php`; `.env` is excluded from the ZIP.
