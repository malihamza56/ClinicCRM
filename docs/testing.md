# Testing

> Results of the actual runs are in [test-report.md](test-report.md). This file explains the strategy and how to run and extend it.

## 1. Strategy

PHPUnit could not be installed in the build environment (no Packagist access), and a student should be able to run the tests with **just PHP**. So the project ships a ~100-line runner (`tests/Support/Tester.php`, `tests/run.php`) with readable assertions (`same`, `true`, `contains`, `throws` …). If you prefer PHPUnit later, the test bodies port directly.

| Layer | Folder | Needs | What it proves |
|---|---|---|---|
| **Unit** | `tests/Unit` | PHP only | pure logic: money arithmetic, validation rules, status machine, password policy, CSV safety, date ranges |
| **Integration** | `tests/Integration` | MySQL/MariaDB | services + SQL against the real schema: permission matrix, constraints/triggers, patients, scheduling, consultations, billing, dashboards/reports, authentication, seed integrity, backup/restore, transaction atomicity |
| **HTTP end-to-end** | `tests/Http` | MySQL + `curl` ext | the whole stack through a real PHP server: login, CSRF, RBAC, record ownership, XSS, SQL injection, CSV export, a full clinic journey, parallel bookings, link/asset/form checking, database outage |
| **Static** | `tests/lint.php` | PHP | every PHP file compiles; no debug calls; no template echoes a bare variable |
| **Browser** | `tests/browser/ui-checks.js` | Node + Chrome | overflow at 390/768/1280 px, accessible names, alt text, one `<h1>`, skip link focus |

### Safety design
* Integration/HTTP tests run in **`DB_TEST_NAME`** (default `clinic_crm_test`), dropped and recreated each run. The runner **refuses to run** if that equals `DB_NAME`.
* Each integration test runs inside a transaction that is rolled back, so tests are independent and leave nothing behind. Groups that must test commit/rollback or whole-database behaviour (atomicity, seed, backup) run unwrapped and clean up after themselves (the seed test uses its own throw-away database).
* HTTP tests start PHP's built-in server on a random port against the test database (with `PHP_CLI_SERVER_WORKERS=6` so the concurrency test is genuinely parallel on Linux/macOS).
* Fixtures (`tests/Support/Fixtures.php`) are fictional. Doctors in tests work around the clock so tests do not depend on the time of day.

## 2. Running

```bash
php tests/run.php                  # all layers
php tests/run.php --unit           # fast, no database
php tests/run.php --integration
php tests/run.php --http
php tests/lint.php
node tests/browser/ui-checks.js http://localhost:8000      # app must be running with demo data
```

Exit code is 0 when everything passes (usable in CI). Composer shortcuts: `composer test`, `composer lint` (Composer is optional).

## 3. Coverage map (prompt "functional tests" 1-15)

| # | Required test area | Where |
|---|---|---|
| 1 | Valid and invalid login | `DashboardAndAuthTest`, `WebTest` (sign-in) |
| 2 | Inactive account rejected | `DashboardAndAuthTest`, `WebTest` |
| 3 | Logout and session invalidation | `WebTest` |
| 4 | Direct URL access by unauthorized roles | `WebTest` (4 roles), `SchemaAndPermissionsTest` (matrix + route table) |
| 5 | Patient create/search/update/archive, duplicates | `PatientTest`, `WebTest` |
| 6 | Doctor/specialty CRUD and account linking | `PlatformChecksTest` |
| 7 | Appointments: create, reschedule, cancel, walk-in, unavailable doctor, overlap, transitions | `AppointmentTest`, `WebTest` journey |
| 8 | Consultation save, validation failures, ownership boundaries, amendments | `VisitTest`, `WebTest` |
| 9 | Prescription with multiple items and print layout | `VisitTest`, `WebTest` (prescription page renders) |
| 10 | Invoice totals, line items, partial/final payment, invalid amounts, overpayment, refund/adjustment, balance consistency | `BillingTest`, seed consistency checks |
| 11 | Follow-up creation and date filtering | `PlatformChecksTest`, `WebTest` |
| 12 | Dashboard counts and financial reports vs known records | `DashboardAndAuthTest` |
| 13 | Validation, SQL injection, XSS, CSRF, authorization bypass | `ValidatorTest`, `WebTest`, `IntegrityTest` |
| 14 | Empty database, missing fields, invalid dates, DB connection failure | `PlatformChecksTest` (empty DB), `ValidatorTest`, `IntegrityTest` (outage) |
| 15 | Responsive layout and keyboard/focus basics | `tests/browser/ui-checks.js` + [manual QA](manual-qa.md) |

Database checks (prompt "Database checks"): foreign keys, primary keys, charset/engine, no floating-point money, constraints, triggers (`SchemaAndPermissionsTest`, `PlatformChecksTest`); seed repeatability and integrity; transaction rollback; backup/restore round trip.

## 4. Do the tests actually catch bugs? (mutation checks)
To make sure tests are not vacuous, protections were deliberately broken and the suite had to fail:

| Mutation | Result |
|---|---|
| CSRF check disabled | 2 HTTP tests failed |
| Permission middleware disabled | 4 more HTTP tests failed |
| Overpayment check disabled | 3 billing tests failed |

(Run once during development; reproduce by editing the line and running the suite.)

## 5. Not covered / blocked (be aware)
* **Idle-session timeout** (needs 30 minutes or an injectable clock) — manual check in [manual-qa.md](manual-qa.md).
* **Real printing / PDF output**, **screen readers**, **colour-blindness simulation**, **very old browsers** — not tested.
* **Windows, XAMPP/Apache, MySQL 8, PHP 8.1/8.2** — not available in the build environment; tests ran on PHP 8.3.6 + MariaDB 10.11 + Linux.
* **Load / performance** — not tested beyond a 1,200-appointment demo dataset (all pages load interactively).
* **Penetration testing** — only the automated hostile-input checks above.

## 6. Adding a test
1. Pick the layer (pure function → Unit; SQL/service → Integration; routing/session/HTML → Http).
2. Copy a neighbouring `*Test.php`; it returns `function (Tester $t) { $t->group(...); $t->test('name', function () use ($t) { ... }); }`.
3. Use `Fixtures::doctor()`, `Fixtures::patient()`, `Fixtures::user('role')` for data.
4. Run the file's layer, then the whole suite. A bug fix should arrive with the regression test that failed first.
