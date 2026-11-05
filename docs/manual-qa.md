# Manual QA checklist

Automated tests cover logic and security (see [testing.md](testing.md)). This checklist is for a **human** to confirm what a computer cannot judge well: look and feel, printing, real browsers, assistive technology, and timing-dependent behaviour.

Mark each box when verified; write the date, browser and tester at the bottom. Items tagged **[auto]** are already covered by an automated check (you may skip them); **[manual]** items were **not** performed by a human during development and must be done before relying on them.

## Setup
- [ ] Fresh install: `php bin/install-db.php --seed`, server running, `.env` present
- [ ] Browsers: current Chrome/Edge, Firefox, Safari (if available), and a phone browser

## 1. Sign-in and accounts
- [ ] **[auto]** wrong password shows a generic message; right password signs in
- [ ] **[auto]** demo account is forced to change its password before anything else
- [ ] **[auto]** 5 wrong attempts lock sign-in with a clear message; correct password still blocked until the wait passes
- [ ] **[manual]** idle timeout: set `SESSION_IDLE_MINUTES=1`, sign in, wait 61 s, click a link → redirected to sign-in with the inactivity notice
- [ ] **[manual]** admin deactivates a user while that user is signed in on another browser → their next click goes to sign-in
- [ ] **[manual]** "Show password" button works; password manager offers to save on the sign-in form
- [ ] Sign out from the user menu; browser Back does not reveal pages (cache is `no-store`)

## 2. Role boundaries (sign in as each demo role)
- [ ] **[auto]** each role sees only its own sidebar links, and every link opens (no 403 dead ends)
- [ ] **[auto]** typing forbidden URLs (e.g. `/users` as receptionist) shows the "Access denied" page with a way back
- [ ] **[manual]** doctor A cannot open doctor B's patient by editing the ID in the address bar
- [ ] **[manual]** accountant never sees diagnosis, symptoms or prescription text anywhere (patient page, invoice create, invoice print)

## 3. Front desk
- [ ] Register a patient with invalid data: phone `12`, future birth date → errors appear next to the fields, typed values are kept
- [ ] Register the same person twice → duplicate warning with a link to the existing record
- [ ] Search by partial name, patient ID and phone; search for `%` and `'` returns "No patient found" (no error)
- [ ] Book an appointment: free times appear after choosing doctor + date; taken times are disabled/struck through; a day without hours says so
- [ ] Open the same booking form in two browser windows, choose the same slot in both, submit both → second shows "slot was just taken"
- [ ] Reschedule; cancel with a reason (confirm dialog explains the slot is freed)
- [ ] Check in a patient (today only); queue number appears; mark another as no-show
- [ ] Walk-in: choose a doctor on duty → joins queue; choose an off-duty doctor → clear message

## 4. Doctor
- [ ] Queue shows only own patients; "Start consultation" opens the form; refresh keeps the draft
- [ ] Save draft, leave, return via "Resume"
- [ ] Finalize with empty diagnosis → blocked with a message; fill and finalize → confirm dialog → success banner, appointment completed
- [ ] Vitals: BP `80/90`, temperature `98.6`, SpO2 `101` are rejected with helpful messages
- [ ] Add 3 medicines, remove one, add a row with a missing dosage → error names the row
- [ ] Amend a finalized visit: reason required; history shows before/after
- [ ] Prescription page: **[manual]** use the browser's print preview — A4, one page, no buttons/sidebar, doctor name and signature line visible
- [ ] Follow-up created at finalization appears on the Follow-ups page

## 5. Billing
- [ ] Create an invoice from a finished visit: doctor fee prefilled, add services, discount; live estimate equals the saved total
- [ ] Record a partial payment, then the rest → status Unpaid → Partial → Paid; receipt prints
- [ ] Try to pay more than the balance → refused with the balance quoted
- [ ] Refund part of a paid invoice (reason required); try refunding more than paid → refused
- [ ] Try to void an invoice that holds money → told to refund first; refund then void
- [ ] **[manual]** invoice print preview: totals aligned, "PAID"/"VOID" stamp, footer text
- [ ] Settings: change currency symbol and tax rate → new invoices use them; old invoices keep their tax snapshot

## 6. Dashboards and reports
- [ ] Change the range presets and custom dates; the heading shows the chosen range
- [ ] Charts have legends and tooltips on hover; a range with no data shows the empty message
- [ ] **[manual]** spot-check one KPI against the list page (e.g. today's completed count vs the filtered Appointments page)
- [ ] CSV exports open in Excel/LibreOffice with correct characters; a cell starting with `=` shows as text

## 7. Look, feel, accessibility
- [ ] **[auto]** no horizontal scrolling at 390 / 768 / 1280 px on 21 pages
- [ ] **[auto]** every form control has a label; images have alt text; one heading level-1 per page; skip link is the first Tab stop
- [ ] Keyboard only: reach every menu item, open the user menu (Enter/Space), submit a form, operate the confirm dialog (Esc cancels)
- [ ] Visible focus ring on every interactive element
- [ ] **[manual]** zoom to 200 % and 400 %: nothing is cut off or overlapping
- [ ] **[manual]** screen reader (NVDA/VoiceOver): banners are announced, form errors are read with their fields, tables have headers
- [ ] **[manual]** OS "reduce motion" on: charts draw instantly, no transitions
- [ ] Compare the admin dashboard with the reference image: colours, card style, chart placement

## 8. Failure modes
- [ ] **[auto]** stop MySQL, reload a page → friendly "Service unavailable" page, no technical text; start MySQL → works again
- [ ] **[manual]** set `APP_DEBUG=true` and trigger an error → details on screen (and `false` hides them)
- [ ] **[manual]** disable JavaScript: sign-in, patient registration, booking (without slot picker the form cannot choose a time — expected), invoices (basic) still load; note which features degrade

## 9. Backup and restore
- [ ] **[auto]** `php bin/backup.php` then `php bin/restore.php file --db=copy` → counts match, triggers present
- [ ] **[manual]** open the restored copy by pointing `DB_NAME` to it and sign in

---
Tester: ____________  Date: __________  Browser/OS: ______________  Result: ☐ pass ☐ pass with notes ☐ fail
