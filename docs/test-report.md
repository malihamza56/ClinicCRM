# Test report

**Application:** Clinic CRM 1.0.0 · **Test date:** 31 Oct 2026 (build environment clock) · **Tester:** automated suite + developer review

## 1. Environment

| Item | Value |
|---|---|
| OS | Ubuntu 24.04 (Linux) |
| PHP | 8.3.6 CLI (built-in web server for HTTP tests) |
| Database | MariaDB 10.11.14 |
| Browser engine for UI checks | headless Chromium (via `@sparticuz/chromium`) driven by `puppeteer-core` |
| Node | used only for browser checks |
| **Not available (BLOCKED)** | Windows, XAMPP/Apache + `.htaccess`, MySQL 5.7/8.x, PHP 8.1/8.2, Firefox/Safari, real printer/PDF output, screen readers |

## 2. Summary

| Layer | Command | Total | Pass | Fail | Blocked |
|---|---|---:|---:|---:|---:|
| Unit | `php tests/run.php --unit` | 24 | 24 | 0 | 0 |
| Integration (real DB) | `php tests/run.php --integration` | 82 | 82 | 0 | 0 |
| HTTP end-to-end | `php tests/run.php --http` | 32 | 32 | 0 | 0 |
| **Automated total** | `php tests/run.php` | **138** | **138** | **0** | 0 |
| Static lint (syntax, debug calls, inline handlers, bare echoes) | `php tests/lint.php` | 157 files | ✅ | 0 | 0 |
| Browser layout/a11y | `node tests/browser/ui-checks.js` | 31 page visits × 3 widths | ✅ | 0 | 0 |
| Crawl of all role pages | ad-hoc script | ~60 page loads, 4 roles | ✅ (0 PHP warnings/errors) | 0 | 0 |
| Manual QA checklist | `docs/manual-qa.md` | — | **not executed by a human** | — | — |

"Pass" means the assertions in the test passed, **not** that the software is free of defects.

## 3. What was checked (by area)

### Unit (24)
Money parsing/formatting/percentage rounding (floating-point drift cases); validator rules (names, emails, phones, dates, ranges, enums, money, array input, unknown rule); appointment status transitions (allowed and forbidden); password policy and temporary passwords; CSV formula neutralisation and BOM; date-range presets, fallbacks and day bucketing.

### Integration (82)
* **Database guarantees:** payments/audit/amendments append-only; invoices and finalized visits undeletable; double-booking blocked at DB level and freed on cancel; duplicate patient key; all tables InnoDB/utf8mb4 with primary keys; no floating-point money columns; FKs never cascade deletes except child rows; CHECK constraints; referenced rows cannot be deleted.
* **Permission matrix:** exact permission set for each of the 5 roles; clinical data only for doctors; money-moving rights only for billing roles; code constants = DB; every route has a real handler and a valid access rule.
* **Patients:** sequential codes, normalisation, duplicate detection, no burned codes on failure, validation, search with wildcard/injection payloads, archive/restore rules, doctor care-relationship scoping.
* **Appointments:** slot generation by schedule and visit length, no slots for off days/past/inactive doctors, booking, double booking, overlap by time range, invalid requests, reschedule, status machine, queue numbers, walk-in rules (duplicate, closed clinic, doctor off duty), schedule validation.
* **Consultations:** start/resume, ownership, finalize (appointment completed, prescription, follow-up), draft saves, amendment history, no-op amendment refused, vitals and medicine validation.
* **Billing:** totals with discount/tax, 0.10×3 exactness, tax rounding, server-side prices, invalid invoices, zero-total invoice, partial/final payment, overpayment/invalid amounts, refunds, adjustments, void rules, one invoice per visit, numbering, rollback.
* **Dashboards/reports vs known data:** every KPI, trend bucket, attendance split, collected/outstanding/refund/adjustment figures, by-method, operational totals, follow-up buckets.
* **Auth/accounts:** generic errors (no enumeration), lockout per email+IP, inactive/patient-role refusal, hashing, password change, admin safeguards, account validation.
* **Platform:** specialty CRUD, doctor + login linking (atomic), doctor search, follow-up date filters, empty-database dashboards/reports, transaction atomicity, **demo seed** loads and is internally consistent (invoice balances, ledger sums, statuses, no double bookings, sequences) and refuses a second run, **backup → restore round trip** (row counts of 8 tables, 8 triggers) and refusal to overwrite.

### HTTP end-to-end (32)
Security headers and cookie flags; 12 URLs redirect to sign-in when anonymous; friendly 404 without leakage; session ID rotates at login; sign-in/lockout/forced password change/sign-out/deactivation; CSRF (missing, forged, foreign-session tokens → 419, no records created); RBAC for 4 roles across many URLs and POSTs; record ownership (doctor vs patient, appointment, visit edit); stored/reflected XSS payloads; SQL-injection payloads on 8 endpoints; CSV formula injection and export auditing; tampered/invalid IDs; **the full journey** (book → duplicate booking refused → check in → consult → finalize → amend → invoice → partial payments → refund → prints → audit log); **6 parallel booking requests → exactly 1 booking**; every link, stylesheet/script/image and form action on each role's pages resolves to a real route/asset; sidebar has no dead ends; 403/404/419 pages; empty states; **database outage → friendly 503 with no credential or stack-trace leakage, cause logged**; doctor/follow-up filters.

### Browser checks (31 page visits × 3 widths)
No horizontal scroll at 390/768/1280 px; every form control has an accessible name; images have alt; exactly one `<h1>`; title and `lang` present; first Tab stop is the visible skip link. Charts were also rendered with reduced-motion emulation and visually compared with the reference image (screenshots in `docs/screenshots`).

### Contrast (computed, WCAG 2.x AA 4.5:1)
All text/background pairs pass after fixes (e.g. ink on white 16.2, muted on canvas 5.3, white on brand green 5.3, warning text 5.4, danger badge 4.8).

### Mutation checks
Disabling the CSRF check, the permission middleware, and the overpayment rule each made the relevant tests fail (2, 4 and 3 failures respectively); the code was restored afterwards.

## 4. Defects found and fixed

| # | Defect | Found by | Root cause → fix |
|---|---|---|---|
| 1 | Dashboard revenue/outstanding/refunds displayed **100× too large** | screenshot review | services return integer minor units but `money()` formats DECIMAL strings → added explicit `money_minor()` and used it for all minor-unit figures |
| 2 | Empty "selected patient" box visible on the booking form | screenshot review | `.selected-patient { display:flex }` overrode the `hidden` attribute → global `[hidden]{display:none!important}` |
| 3 | Mobile menu button visible on desktop | screenshot review | `.btn` display overrode `.menu-toggle` → more specific selector |
| 4 | Horizontal scrolling on phones for two-column pages (dashboard, queue, invoice, reports…) | overflow measurement | CSS-grid min-content blow-out → `minmax(0,1fr)` + `min-width:0` on grid children |
| 5 | CLI scripts exited with **status 0 after refusing/failing** (e.g. `restore.php` refusing to overwrite; weak password in `create-admin.php`) | backup/restore integration test | `exit("message")` exits 0 → `fail()` helper with STDERR + exit 1 |
| 6 | Patient search box offered "Register a new patient" to doctors/accountants (leads to 403) | link/route checker | link shown regardless of permission → shown only with `patients.create` |
| 7 | Contrast below AA: muted text on the tinted canvas (4.2:1) and orange status text (3.5-3.8:1) | computed contrast review | darker `--muted` and `--orange-600` tokens |
| 8 | An inline `onchange` handler on the queue filter would have been blocked by the app's own CSP | self-review while writing the CSP test | replaced by `data-autosubmit` handled in `app.js`; `tests/lint.php` now fails on inline scripts and `on*=` attributes |
| 9 | Doctors page had no search/filter; follow-ups had no date-filter UI; four unused methods (dead code) | requirements audit against `prompt.md` | added `Doctor::search` + filters, follow-up date filter, removed dead code; tests added |
| 10 | Large KPI values wrapped onto two lines; prescription header misaligned with its rows | screenshot review | `nowrap` + compact money format; fixed grid columns |

During test authoring several *test* mistakes were also corrected (e.g. a wrong rounding expectation, PHP `+` array-merge semantics, a form checker that assumed every form is POST); these were not product defects.

## 5. Known issues and limitations (not fixed)
1. **BLOCKED / untested environments:** Windows, XAMPP, Apache `.htaccess`, MySQL 8, PHP 8.1/8.2, other browsers. Treat `docs/setup.md` Windows steps as unverified.
2. **Idle-session expiry** has no automated test (manual checklist item).
3. **Booking requires JavaScript** (the slot picker is rendered by `app.js`); other core pages work without it.
4. **No password reset by email**, **no notifications**, **single-clinic queries** (see README).
5. **Print output** only inspected as HTML; **screen readers** not tested; **no external penetration test**.
6. The concurrency test is truly parallel only where PHP's built-in server supports workers (Linux/macOS); on Windows it runs sequentially.
7. Demo data dates are relative to the day of seeding; the dashboard's "today" tiles are empty on days when no doctor has hours (every demo doctor has hours every day, so this should not occur).
8. Backup/restore requires the `mysqldump`/`mysql` binaries (on `PATH` or configured in `.env`); verified only with MariaDB's client tools.

## 6. Reproducing this report
```bash
cp .env.example .env
php bin/install-db.php --seed
php tests/run.php            # 138 passed
php tests/lint.php
php -S localhost:8000 -t public public/router.php &
node tests/browser/ui-checks.js http://localhost:8000
```
(Demo accounts must have had their password changed for the browser check to be able to sign in: run `UPDATE users SET must_change_password = 0` on a *development* database first, or change each password once.)
