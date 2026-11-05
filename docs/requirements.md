# Requirements and traceability

Source of requirements: the master prompt (`prompt.md`) and the supplied dashboard reference image. Status legend: ✅ implemented and tested · ◐ implemented with a documented limitation · ⛔ deliberately not implemented (documented).

## 1. Product decisions

| Decision | Status | Notes |
|---|---|---|
| Name **Clinic CRM**, multi-specialty clinic, web based | ✅ | |
| One clinic now, extensible to several | ◐ | `clinics` table + `clinic_id` on users/patients/services/invoices/settings; queries are not yet clinic-scoped |
| Patient portal **not** in MVP, data model extensible | ✅ | `patient` role reserved (cannot sign in), `patients.user_id` reserved; not shown as a feature |
| Billing = consultation fees + services + full/partial payments | ✅ | |
| PHP 8+, MySQL/MariaDB, HTML/CSS/JS, Chart.js | ✅ | Bootstrap CSS intentionally not used (supplied tokens are small; see architecture.md) |
| Windows + XAMPP + VS Code + phpMyAdmin; deployable to ordinary hosting | ◐ | documented in setup.md; **not verified on Windows** |
| Modular MVC-style, beginner readable, no large framework | ✅ | |
| ZIP with top-level directory `Clinic CRM/` | ✅ | |

## 2. Functional requirements

### 2.1 Authentication and accounts
| Requirement | Status | Where / how tested |
|---|---|---|
| Login, logout, session validation, activation/deactivation | ✅ | `AuthService`, `AuthMiddleware`; HTTP tests (sign-in, sign-out, deactivated user loses access immediately) |
| `password_hash` / `password_verify`, rehash | ✅ | `AuthService`; integration test (hash stored, 60 chars, never plain) |
| Session ID regenerated at login; cookie flags | ✅ | `Auth::login`, `Session::start`; HTTP test (cookie changes; HttpOnly, SameSite=Lax) |
| Lockout without easy DoS | ✅ | per email+IP; integration + HTTP tests (other IP unaffected) |
| Generic login errors | ✅ | integration test (same text for unknown email and wrong password) |
| Server-side authorization everywhere | ✅ | deny-by-default router; route-table test; RBAC HTTP tests |
| No default production admin; demo credentials marked and forced to change | ✅ | `bin/create-admin.php`; seed accounts `must_change_password=1` (seed test) |
| Password reset | ⛔ | deferred (no mail server); admin issues temporary passwords; documented |

### 2.2 Patients
| Requirement | Status | Notes |
|---|---|---|
| Create, view, search, update, archive | ✅ | archive blocked while future appointments exist; restore available |
| Unique human-readable ID | ✅ | `PT-000123` from a locked sequence |
| Only necessary data; validation (formats, dates, lengths) | ✅ | `PatientService::validate`; unit/integration tests |
| Duplicate prevention; name is not a key | ✅ | unique (name, DOB, phone) + service check pointing to the existing record |
| History preserved on archive | ✅ | tested |
| Search by ID, name, phone, parameterized | ✅ | wildcard escaping and injection payloads tested |

### 2.3 Specialties and doctors
| Requirement | Status | Notes |
|---|---|---|
| Manage specialties and doctor profiles; link profile to login | ✅ | `DoctorService::createWithAccount` (atomic; tested) |
| Specialization, contact, fee, active status, schedule | ✅ | plus room, license, visit length |
| Prevent conflicting/invalid schedules | ✅ | overlap, clinic-hours and time-order checks; DB `CHECK`s |
| Auth data separate from professional data | ✅ | `users` vs `doctors` |

### 2.4 Appointments
| Requirement | Status | Notes |
|---|---|---|
| Create, view, reschedule, cancel, status updates | ✅ | |
| Validate dates, clinic hours, availability, overlaps | ✅ | slot engine + overlap tests |
| Walk-ins without duplicate patient records | ✅ | choose existing patient (search) or register through the duplicate-checked form |
| Queue tracking | ✅ | per-doctor numbers; live queue page |
| Status separate from visit/invoice status; valid transitions documented/enforced | ✅ | workflow.md; status tests |
| Cancellation reason | ✅ | required |
| Safe against double booking, including concurrent requests | ✅ | row lock + unique key; 6-parallel-request HTTP test (needs PHP workers; sequential on Windows) |

### 2.5 Visits and medical records
| Requirement | Status | Notes |
|---|---|---|
| Visit linked to patient, doctor, appointment | ✅ | |
| Walk-in visits without a pre-existing booking | ✅ | walk-in is an appointment of type *Walk-in*; `visits.appointment_id` nullable for future use |
| Complaint, symptoms, vitals, diagnosis, plan, notes, follow-up advice | ✅ | |
| Plausible-range vitals | ✅ | unit/integration tests; not medical advice |
| Access by role and care relationship | ✅ | `AccessPolicy`; HTTP tests |
| Amendment history; no silent overwrite | ✅ | `visit_amendments` + triggers |
| No hard delete of finalized records | ✅ | no route; DB trigger |

### 2.6 Prescriptions
Multiple items (medicine, dosage, frequency, duration, instructions) ✅ · validation and length limits ✅ · print-friendly view ✅ (HTML verified, not printed) · no medical-advice claim ✅ (footer says valid only with signature; no drug checks).

### 2.7 Services, invoices, payments
| Requirement | Status | Notes |
|---|---|---|
| Configurable fees/services | ✅ | doctor fee + services list; price snapshot on invoice |
| Multi-line invoices; DECIMAL + integer arithmetic | ✅ | `Money`; exactness tests |
| Subtotal, discount/tax if configured, total, paid, balance on the server | ✅ | flat discount; one tax rate setting (0 by default) |
| Unpaid / partial / paid; multiple payments | ✅ | |
| Date, amount, method, reference/receipt, recording user | ✅ | `payments` |
| No negative amounts; overpayment policy | ✅ | overpayment refused (documented) |
| Transactions for payment posting | ✅ | invoice row lock; rollback test |
| No silent deletion; refunds/voids/adjustments traceable | ✅ | append-only ledger |
| Printable invoice/receipt | ✅ | |
| Reports from persisted data | ✅ | tests compare with known data |
| Configurable currency, default PKR/Rs. | ✅ | settings page |

### 2.8 Follow-ups
Record date, notes, patient, visit, doctor ✅ · lists for upcoming, due, completed, overdue ✅ · date filtering ✅ (UI + tests) · notifications ⛔ (not claimed; on-screen only).

### 2.9 Dashboards
| Role | Delivered |
|---|---|
| Admin | active patients, today's appointments by outcome, ongoing, active doctors, new registrations in range, outstanding balance, revenue in range, trend and outcome charts, schedule, outstanding invoices, follow-up counts |
| Doctor | own appointments, own queue (start/resume), own follow-ups, recent consultations |
| Receptionist | appointments, queue, expected arrivals, registration/booking/walk-in shortcuts, follow-up counts |
| Accountant | revenue, outstanding, invoices issued, refunds, collections chart, payment methods, largest open balances |

Charts have axes, legends, tooltips, responsive containers and empty states ✅ · every number derived from SQL ✅ · date filters with the chosen range printed ✅.

### 2.10 Search, filters, exports, audit
Search/filters for patients ✅, appointments ✅, doctors ✅, invoices ✅, payments ✅, audit ✅, follow-ups ✅ · CSV exports for patients, appointments, invoices, payments, authorized per permission, formula-injection protected ✅ · audit of security, clinical and financial events ✅ · backup/restore tooling documented **and tested** ✅.

## 3. UX/UI requirements
| Requirement | Status | Evidence |
|---|---|---|
| Follow the supplied reference (dashboard) | ✅ | tokens, KPI cards, trend + donut, sidebar; see assumptions below |
| Calm, readable, not flashy | ✅ | one brand colour; orange/blue/red used only for meaning |
| Contrast, focus, labels, semantic HTML, keyboard | ✅ (automated) | all text pairs ≥ 4.5:1 (computed); skip link; every control labelled (browser check) |
| Subtle transitions; `prefers-reduced-motion` | ✅ | CSS and chart animation both respect it |
| Responsive desktop/tablet/mobile | ✅ | no horizontal overflow at 390/768/1280 px (browser check) |
| Loading, success, validation, permission-denied, empty, server-error states | ✅ | button spinner and slot loader; flash banners; field errors; 403/404/419/503/500 pages; empty states with illustrations |
| Confirm destructive actions | ✅ | dialog with consequences (archive, deactivate, void, refund, cancel, finalize …) |
| No invented logos/branding conflicting with assets | ✅ | no logo was supplied; a simple neutral mark is used |
| No heavy frameworks, glassmorphism, decorative charts | ✅ | |

## 4. Technical requirements
MVC + documented request flow ✅ · no DB access in views ✅ · PDO prepared statements ✅ · centralized config/routing/auth/authz/validation/errors/helpers ✅ · thin controllers ✅ · transactions ✅ · `.env.example` + documented loader ✅ · `.gitignore` ✅ · documented schema process ✅ · fictional seed ✅ · no secrets committed ✅ (scan documented) · no "bug-free" claims ✅ (see test report).

## 5. Assumptions
1. **Only one design asset was supplied** — the dashboard reference image. No separate token file, palette file or login reference arrived. Colours were sampled from the image (deep clinical green primary; orange, blue and red reserved for meaning; sage-tinted canvas), fonts were chosen to match its geometric headings (Plus Jakarta Sans) with Inter for dense tables, and the login page applies the same system. The reference's violet "active menu" tint was replaced by the brand green to keep one accent colour.
2. **Images:** no photographs were supplied or downloadable. Illustrations (login panel, empty states, error pages) are original inline SVGs; avatars are initials.
3. **Currency PKR (`Rs.`)**, time zone `Asia/Karachi`, 24-hour clinic hours 09:00-21:00 by default — all configurable.
4. **Care-relationship policy** for doctors (see security.md) and **admin without clinical access** are policy choices; they are implemented in one class (`AccessPolicy`) and one matrix.
5. **Overpayment refused**, **no credit balances**, **discount is a flat amount**, **single tax rate** — simplest policies that keep balances consistent.
6. **Follow-ups are on-screen reminders**; sending messages is future work.
7. **Walk-ins require the doctor to be on duty now** and the clinic to be open.
