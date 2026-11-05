# Migrations

`database/schema.sql` is the **baseline (version 1)** and always describes the full current structure for fresh installs.

When you must change the structure of a database that is already in use:

1. Create a new file here named `NNN_short_description.sql` with the next number, e.g. `002_add_patient_notes.sql`.
   Write idempotent-friendly, additive SQL (`ALTER TABLE ... ADD COLUMN ...`, `CREATE TABLE IF NOT EXISTS ...`).
2. Apply it to every existing database (phpMyAdmin *Import*, or `mysql -u user -p clinic_crm < database/migrations/002_...sql`) **after taking a backup** (`php bin/backup.php`).
3. Update `database/schema.sql` so a fresh install ends up identical.
4. Add or adjust tests, then run `php tests/run.php`.
5. Record the change in `docs/implementation-status.md`.

Never edit an old migration that has been applied somewhere; add a new one.

There are no migrations beyond the baseline yet.
