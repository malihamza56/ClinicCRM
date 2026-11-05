# Setup guide

> **Verification status.** The steps in *Option A (command line)* and the backup/restore section were executed and tested on Linux (PHP 8.3.6, MariaDB 10.11). The **Windows / XAMPP / Apache** steps (Option B and C) follow the official behaviour of those tools but were **not** run on Windows — if something differs on your machine, the [troubleshooting](#troubleshooting) section lists the usual causes.

## 1. What you need

| Requirement | Version | Notes |
|---|---|---|
| PHP | 8.1 or newer | Extensions: `pdo_mysql`, `mbstring` (both on by default in XAMPP). `curl` only for the HTTP tests. |
| MySQL or MariaDB | MySQL 5.7+/8.x, MariaDB 10.4+ | Needs `InnoDB`, generated columns, triggers. `CHECK` constraints are enforced from MySQL 8.0.16 / MariaDB 10.2.1 (older versions silently ignore them — the PHP code validates the same rules). |
| A browser | any current one | |
| Git, VS Code | optional | |
| Node.js | optional | Only for `tests/browser/ui-checks.js`. |
| Composer | **not needed** | The project has its own tiny autoloader. `composer.json` exists for metadata and shortcut scripts. |

## 2. Get the project

Unzip so you have a folder named `Clinic CRM`. **Do not put it inside `htdocs` and open it through `http://localhost/Clinic CRM/`** — then `app/`, `config/`, `.env` and `database/` sit inside the public web space. The safe layout is: project anywhere (e.g. `C:\Projects\Clinic CRM`), web server pointed **only** at its `public` folder.

(Safety nets exist — `.htaccess` files deny the private folders and `.env` — but "don't expose them in the first place" is the rule.)

## 3. Configure

```bash
cp .env.example .env        # Windows: copy .env.example .env
```

Open `.env` and check at least:

```ini
APP_ENV=local
APP_DEBUG=true              # shows error details - NEVER true on a real server
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=clinic_crm
DB_USER=root                # XAMPP default; use a dedicated user on a real server (section 8)
DB_PASS=                    # XAMPP default is empty
DB_TEST_NAME=clinic_crm_test
```

> PHP does **not** read `.env` files by itself. Clinic CRM reads it (`app/Core/Env.php`). Real environment variables, if set, win over the file.

## 4. Option A - command line (fastest; tested)

```bash
php bin/install-db.php --seed        # creates the database, imports schema.sql, loads demo data
php -S localhost:8000 -t public public/router.php
```

Open <http://localhost:8000>.

* `php bin/install-db.php` (no `--seed`) installs an **empty** system. Then create the first administrator:
  `php bin/create-admin.php "Full Name" you@clinic.com` (the password is typed at the prompt, never stored in a file).
* `php bin/install-db.php --fresh` drops and recreates the database after you type its name.
* The script refuses to run on a non-empty database, so it cannot overwrite data by accident.

### Demo accounts (development only)

Password for **all** demo accounts: `ChangeMe!2026` — each account must replace it at first sign-in.

| Role | Email |
|---|---|
| Clinic Admin | `admin@demo-clinic.test` |
| Receptionist | `reception@demo-clinic.test`, `reception2@demo-clinic.test` |
| Accountant | `accounts@demo-clinic.test` |
| Doctors | `ayesha@`, `bilal@`, `sana@`, `imran@`, `hira@`, `farhan@demo-clinic.test` |

Demo dates are generated relative to the day you run the seeder (history for the last 45 days, today's queue, next week's bookings). Seeding twice is prevented (`seedDemoData()` stops if users exist).

## 5. Option B - XAMPP on Windows with phpMyAdmin (untested on Windows)

1. Install XAMPP (PHP 8.1+). Start **Apache** and **MySQL** in the XAMPP Control Panel.
2. Put the project in e.g. `C:\Projects\Clinic CRM`. Add `C:\xampp\php` to your `PATH` so `php` works in a terminal.
3. **Database via phpMyAdmin:** open <http://localhost/phpmyadmin> → *New* → name `clinic_crm`, collation `utf8mb4_unicode_ci` → open it → *Import* → choose `database/schema.sql` → *Import*. (The file contains `DELIMITER $$` blocks for triggers; current phpMyAdmin handles them. If you see an error near `DELIMITER`, use `php bin/install-db.php` instead.)
4. Copy `.env.example` to `.env` (section 3).
5. Optional demo data: `php bin/seed-demo.php` (works on an already-imported schema; refuses if users exist).
6. For a real, empty installation: `php bin/create-admin.php "Full Name" you@clinic.com`.
7. **Point Apache at `public/`** (virtual host):

   `C:\xampp\apache\conf\extra\httpd-vhosts.conf` — keep the default `localhost` host *first*, then add:

   ```apache
   <VirtualHost *:80>
       ServerName clinic-crm.local
       DocumentRoot "C:/Projects/Clinic CRM/public"
       <Directory "C:/Projects/Clinic CRM/public">
           AllowOverride All
           Require all granted
       </Directory>
   </VirtualHost>
   ```

   Add `127.0.0.1  clinic-crm.local` to `C:\Windows\System32\drivers\etc\hosts` (edit as Administrator). Make sure `mod_rewrite` is loaded and `httpd-vhosts.conf` is included in `httpd.conf` (both are the XAMPP defaults). Restart Apache and open <http://clinic-crm.local>.

## 6. Option C - Apache sub-folder (not recommended)

`http://localhost/Clinic%20CRM/public/` works because the app detects its base path and `public/.htaccess` rewrites to `index.php`, and `.htaccess` files in the private folders deny access. It is supported for convenience only; use a virtual host for anything beyond a quick look.

## 7. Verify the installation

```bash
php tests/run.php --unit        # no database needed
php tests/run.php               # full suite; creates and drops DB_TEST_NAME
php tests/lint.php
```

Expected: all green (136 tests at the time of writing). The runner **refuses to start** if `DB_TEST_NAME` equals `DB_NAME`. On Windows the HTTP tests need `extension=curl` in `php.ini`; PHP's built-in server ignores `PHP_CLI_SERVER_WORKERS` on Windows, so the concurrency test then runs sequentially (it still passes, but proves less).

Manual smoke test: sign in as the admin → change the temporary password → open *Dashboard*, *Patients*, *Appointments*, *Queue*; sign out; sign in as `ayesha@demo-clinic.test`.

## 8. Production notes (summary - see security.md for the checklist)

* `APP_ENV=production`, `APP_DEBUG=false`, serve over **HTTPS** only, `SESSION_COOKIE_SECURE=true`.
* Create a dedicated database user instead of `root`:

  ```sql
  CREATE USER 'clinic_app'@'localhost' IDENTIFIED BY 'a-long-random-password';
  GRANT SELECT, INSERT, UPDATE, DELETE ON clinic_crm.* TO 'clinic_app'@'localhost';
  ```

  (The installer, tests and backups need more privileges; run those as an administrative user.)
* Install **without** demo data; create the first admin with `bin/create-admin.php`.
* Make `storage/logs` writable by the web server and nothing else; rotate logs.
* Schedule backups (below) and test a restore.
* Sessions are stored in PHP's default files; for several web servers use shared session storage.

## 9. Backup and restore

```bash
php bin/backup.php                       # storage/backups/clinic_crm-YYYYmmdd-HHMMSS.sql
php bin/backup.php --out=/safe/place/clinic.sql
php bin/restore.php /safe/place/clinic.sql --db=clinic_crm_copy     # into another database
php bin/restore.php /safe/place/clinic.sql --force                  # replace the live database (asks you to type its name)
```

* Uses `mysqldump` / `mysql`. If they are not on your `PATH` set `MYSQLDUMP_PATH` / `MYSQL_PATH` in `.env` (XAMPP: `C:\xampp\mysql\bin\...`; the scripts also look there by default on Windows).
* The password is passed through an environment variable, not on the command line.
* Dumps include tables, data, **triggers** and routines. The restore script refuses to restore into a database that already contains tables unless you use `--force`.
* **Tested:** a seeded database (1,200+ appointments) was backed up and restored into a second database; row counts of eight core tables and all 8 triggers matched, and a second restore into the same target was refused (automated test in `tests/Integration/PlatformChecksTest.php`).
* Backups contain patient data. Store them encrypted, off the server, with limited access, and delete them according to your retention policy.
* **Schedule it:** Windows Task Scheduler (`php.exe C:\Projects\Clinic CRM\bin\backup.php`) or cron (`0 2 * * * php /path/bin/backup.php`).

## 10. Changing the database later (migrations)

`database/schema.sql` always describes the **current full schema** for fresh installs. When you change the structure after a system is live, add a numbered SQL file to `database/migrations/` (see the README there), update `schema.sql` to match, and run the migration on existing databases. The baseline (`schema.sql`) is version 1.

## Troubleshooting

| Symptom | Likely cause and fix |
|---|---|
| `could not find driver` | Enable `extension=pdo_mysql` in `php.ini` (XAMPP: `C:\xampp\php\php.ini`), restart Apache. Check with `php -m`. |
| "Service unavailable - the clinic database is not reachable" | MySQL is not running, or `.env` host/port/user/password is wrong. The cause is in `storage/logs/app.log` (never on screen unless `APP_DEBUG=true`). |
| `Access denied for user 'root'` | Your MySQL root has a password: set `DB_PASS` in `.env`. |
| Page unstyled / no icons | The web root is not `public/`. Check the virtual host `DocumentRoot`. |
| 404 on every page except `/` | `mod_rewrite` is off or `AllowOverride All` is missing for `public/`. |
| 403 Forbidden on `/` | Apache `Require all granted` missing in the `<Directory>` block, or wrong path. |
| "Form expired" (419) after a long pause | The session ended or cookies are blocked. Reload the page and retry. Idle sessions expire after `SESSION_IDLE_MINUTES` (default 30). |
| Import error near `DELIMITER` | Old phpMyAdmin: use `php bin/install-db.php`. |
| `Unknown column` / generated column error | MariaDB older than 10.2.2 / MySQL older than 5.7 lack stored generated columns. Upgrade the database server. |
| Port 80 busy (Skype, IIS) | Stop that program, or change Apache's `Listen` port. |
| Times are off by hours | Set `APP_TIMEZONE` in `.env` (default `Asia/Karachi`). |
| `.env` changes ignored | Check the file is named exactly `.env` (not `.env.txt`) in the project root. |
| `bin/backup.php`: "Could not start mysqldump" | Add MySQL's `bin` folder to `PATH` or set `MYSQLDUMP_PATH`. |
