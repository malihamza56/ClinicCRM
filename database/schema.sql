-- =====================================================================
-- Clinic CRM - database schema (MySQL 5.7+/8.x and MariaDB 10.4+)
--
-- Safe to run on an EMPTY database only. Create the database first, e.g.
--   CREATE DATABASE clinic_crm CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- then import this file (phpMyAdmin > Import, or: php bin/install-db.php).
--
-- Contains: tables, constraints, append-only triggers and the fixed reference
-- data (roles, permissions, role-permission matrix, default settings).
-- It contains NO user accounts and NO passwords.
-- =====================================================================

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------
-- Clinics (one row today; every business table can later be scoped to a clinic)
-- ---------------------------------------------------------------------
CREATE TABLE clinics (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name       VARCHAR(150) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Roles and permissions (RBAC)
-- ---------------------------------------------------------------------
CREATE TABLE roles (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    code        VARCHAR(30)  NOT NULL,
    name        VARCHAR(60)  NOT NULL,
    description VARCHAR(255) NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_roles_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE permissions (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    code        VARCHAR(60)  NOT NULL,
    description VARCHAR(255) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_permissions_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE role_permissions (
    role_id       INT UNSIGNED NOT NULL,
    permission_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (role_id, permission_id),
    CONSTRAINT fk_rp_role       FOREIGN KEY (role_id)       REFERENCES roles (id)       ON DELETE CASCADE,
    CONSTRAINT fk_rp_permission FOREIGN KEY (permission_id) REFERENCES permissions (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Staff accounts (authentication data only - professional data lives in `doctors`)
-- ---------------------------------------------------------------------
CREATE TABLE users (
    id                   INT UNSIGNED NOT NULL AUTO_INCREMENT,
    clinic_id            INT UNSIGNED NOT NULL DEFAULT 1,
    role_id              INT UNSIGNED NOT NULL,
    full_name            VARCHAR(120) NOT NULL,
    email                VARCHAR(190) NOT NULL,
    phone                VARCHAR(20)  NULL,
    password_hash        VARCHAR(255) NOT NULL,
    is_active            TINYINT(1)   NOT NULL DEFAULT 1,
    must_change_password TINYINT(1)   NOT NULL DEFAULT 0,
    last_login_at        DATETIME NULL,
    password_changed_at  DATETIME NULL,
    created_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_email (email),
    KEY idx_users_role (role_id),
    CONSTRAINT fk_users_clinic FOREIGN KEY (clinic_id) REFERENCES clinics (id),
    CONSTRAINT fk_users_role   FOREIGN KEY (role_id)   REFERENCES roles (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Failed/successful sign-ins, used for lockout. Only the email text typed is stored, never passwords.
CREATE TABLE login_attempts (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    email        VARCHAR(190) NOT NULL,
    ip_address   VARCHAR(45)  NOT NULL,
    succeeded    TINYINT(1)   NOT NULL DEFAULT 0,
    attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_attempts_pair (email, ip_address, attempted_at),
    KEY idx_attempts_ip (ip_address, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE settings (
    clinic_id     INT UNSIGNED NOT NULL DEFAULT 1,
    setting_key   VARCHAR(60)  NOT NULL,
    setting_value VARCHAR(500) NOT NULL DEFAULT '',
    updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (clinic_id, setting_key),
    CONSTRAINT fk_settings_clinic FOREIGN KEY (clinic_id) REFERENCES clinics (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Gap-free, race-safe counters for human-readable numbers (patient code, invoice no, receipt no ...).
CREATE TABLE number_sequences (
    name       VARCHAR(30) NOT NULL,
    last_value BIGINT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Specialties, doctors and their weekly schedules
-- ---------------------------------------------------------------------
CREATE TABLE specialties (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name        VARCHAR(100) NOT NULL,
    description VARCHAR(255) NULL,
    is_active   TINYINT(1)   NOT NULL DEFAULT 1,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_specialties_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE doctors (
    id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id          INT UNSIGNED NOT NULL,
    specialty_id     INT UNSIGNED NOT NULL,
    license_no       VARCHAR(50)  NULL,
    qualification    VARCHAR(150) NULL,
    phone            VARCHAR(20)  NULL,
    room             VARCHAR(30)  NULL,
    consultation_fee DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    slot_minutes     SMALLINT UNSIGNED NOT NULL DEFAULT 15,
    is_active        TINYINT(1)   NOT NULL DEFAULT 1,
    created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_doctors_user (user_id),
    KEY idx_doctors_specialty (specialty_id),
    CONSTRAINT fk_doctors_user      FOREIGN KEY (user_id)      REFERENCES users (id),
    CONSTRAINT fk_doctors_specialty FOREIGN KEY (specialty_id) REFERENCES specialties (id),
    CONSTRAINT chk_doctors_fee  CHECK (consultation_fee >= 0),
    CONSTRAINT chk_doctors_slot CHECK (slot_minutes BETWEEN 5 AND 120)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- weekday uses ISO numbers: 1 = Monday ... 7 = Sunday
CREATE TABLE doctor_schedules (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    doctor_id  INT UNSIGNED NOT NULL,
    weekday    TINYINT UNSIGNED NOT NULL,
    start_time TIME NOT NULL,
    end_time   TIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_schedule_doctor_day (doctor_id, weekday),
    CONSTRAINT fk_schedule_doctor FOREIGN KEY (doctor_id) REFERENCES doctors (id) ON DELETE CASCADE,
    CONSTRAINT chk_schedule_weekday CHECK (weekday BETWEEN 1 AND 7),
    CONSTRAINT chk_schedule_times   CHECK (end_time > start_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Patients
-- user_id is reserved for the future patient portal (one login per patient).
-- ---------------------------------------------------------------------
CREATE TABLE patients (
    id                      INT UNSIGNED NOT NULL AUTO_INCREMENT,
    clinic_id               INT UNSIGNED NOT NULL DEFAULT 1,
    patient_code            VARCHAR(20)  NOT NULL,
    first_name              VARCHAR(80)  NOT NULL,
    last_name               VARCHAR(80)  NOT NULL,
    gender                  ENUM('Male','Female','Other') NOT NULL,
    date_of_birth           DATE NOT NULL,
    phone                   VARCHAR(20)  NOT NULL,
    email                   VARCHAR(190) NULL,
    address                 VARCHAR(255) NULL,
    city                    VARCHAR(80)  NULL,
    blood_group             ENUM('A+','A-','B+','B-','AB+','AB-','O+','O-') NULL,
    emergency_contact_name  VARCHAR(120) NULL,
    emergency_contact_phone VARCHAR(20)  NULL,
    registered_on           DATE NOT NULL,
    is_archived             TINYINT(1)   NOT NULL DEFAULT 0,
    archived_at             DATETIME NULL,
    archived_by             INT UNSIGNED NULL,
    user_id                 INT UNSIGNED NULL,
    created_by              INT UNSIGNED NOT NULL,
    created_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_patients_code (patient_code),
    UNIQUE KEY uq_patients_user (user_id),
    -- Duplicate guard: same person (name + date of birth) with the same phone cannot be registered twice.
    UNIQUE KEY uq_patients_identity (first_name, last_name, date_of_birth, phone),
    KEY idx_patients_name (last_name, first_name),
    KEY idx_patients_phone (phone),
    KEY idx_patients_registered (registered_on),
    CONSTRAINT fk_patients_clinic   FOREIGN KEY (clinic_id)   REFERENCES clinics (id),
    CONSTRAINT fk_patients_user     FOREIGN KEY (user_id)     REFERENCES users (id),
    CONSTRAINT fk_patients_creator  FOREIGN KEY (created_by)  REFERENCES users (id),
    CONSTRAINT fk_patients_archiver FOREIGN KEY (archived_by) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Appointments
-- `active_slot` is a generated column: it holds the start time only while a booked
-- (non walk-in) appointment still occupies the slot. The UNIQUE key on it makes the
-- DATABASE reject two bookings for the same doctor/day/start time, even for simultaneous requests.
-- ---------------------------------------------------------------------
CREATE TABLE appointments (
    id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
    patient_id          INT UNSIGNED NOT NULL,
    doctor_id           INT UNSIGNED NOT NULL,
    appointment_date    DATE NOT NULL,
    start_time          TIME NOT NULL,
    end_time            TIME NOT NULL,
    booking_type        ENUM('Scheduled','Walk-in') NOT NULL DEFAULT 'Scheduled',
    status              ENUM('Scheduled','Confirmed','Waiting','In Progress','Completed','Cancelled','No Show') NOT NULL DEFAULT 'Scheduled',
    reason              VARCHAR(255) NULL,
    cancellation_reason VARCHAR(255) NULL,
    queue_number        SMALLINT UNSIGNED NULL,
    checked_in_at       DATETIME NULL,
    created_by          INT UNSIGNED NOT NULL,
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    active_slot         TIME GENERATED ALWAYS AS (
        IF(booking_type = 'Scheduled' AND status NOT IN ('Cancelled','No Show'), start_time, NULL)
    ) STORED,
    PRIMARY KEY (id),
    UNIQUE KEY uq_appointments_slot (doctor_id, appointment_date, active_slot),
    KEY idx_appointments_day (appointment_date, doctor_id),
    KEY idx_appointments_patient (patient_id),
    KEY idx_appointments_status (status),
    CONSTRAINT fk_appt_patient FOREIGN KEY (patient_id) REFERENCES patients (id),
    CONSTRAINT fk_appt_doctor  FOREIGN KEY (doctor_id)  REFERENCES doctors (id),
    CONSTRAINT fk_appt_creator FOREIGN KEY (created_by) REFERENCES users (id),
    CONSTRAINT chk_appt_times  CHECK (end_time > start_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Visits (consultations) and prescriptions
-- A visit has its own status (Draft/Finalized), separate from the appointment status.
-- ---------------------------------------------------------------------
CREATE TABLE visits (
    id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    patient_id       INT UNSIGNED NOT NULL,
    doctor_id        INT UNSIGNED NOT NULL,
    appointment_id   INT UNSIGNED NULL,
    visited_at       DATETIME NOT NULL,
    chief_complaint  VARCHAR(500) NULL,
    symptoms         TEXT NULL,
    bp_systolic      SMALLINT UNSIGNED NULL,
    bp_diastolic     SMALLINT UNSIGNED NULL,
    pulse_bpm        SMALLINT UNSIGNED NULL,
    temperature_c    DECIMAL(4,1) NULL,
    respiratory_rate SMALLINT UNSIGNED NULL,
    spo2_percent     TINYINT UNSIGNED NULL,
    weight_kg        DECIMAL(5,1) NULL,
    height_cm        DECIMAL(5,1) NULL,
    diagnosis        TEXT NULL,
    treatment_plan   TEXT NULL,
    notes            TEXT NULL,
    follow_up_advice VARCHAR(255) NULL,
    status           ENUM('Draft','Finalized') NOT NULL DEFAULT 'Draft',
    finalized_at     DATETIME NULL,
    created_by       INT UNSIGNED NOT NULL,
    created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_visits_appointment (appointment_id),
    KEY idx_visits_patient (patient_id, visited_at),
    KEY idx_visits_doctor (doctor_id, visited_at),
    CONSTRAINT fk_visits_patient     FOREIGN KEY (patient_id)     REFERENCES patients (id),
    CONSTRAINT fk_visits_doctor      FOREIGN KEY (doctor_id)      REFERENCES doctors (id),
    CONSTRAINT fk_visits_appointment FOREIGN KEY (appointment_id) REFERENCES appointments (id),
    CONSTRAINT fk_visits_creator     FOREIGN KEY (created_by)     REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Every change to a FINALIZED visit is stored here (before and after), together with who and why.
CREATE TABLE visit_amendments (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    visit_id      INT UNSIGNED NOT NULL,
    amended_by    INT UNSIGNED NOT NULL,
    reason        VARCHAR(500) NOT NULL,
    previous_data LONGTEXT NOT NULL,
    new_data      LONGTEXT NOT NULL,
    amended_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_amendments_visit (visit_id),
    CONSTRAINT fk_amend_visit FOREIGN KEY (visit_id)   REFERENCES visits (id),
    CONSTRAINT fk_amend_user  FOREIGN KEY (amended_by) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE prescriptions (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    visit_id        INT UNSIGNED NOT NULL,
    patient_id      INT UNSIGNED NOT NULL,
    doctor_id       INT UNSIGNED NOT NULL,
    prescription_no VARCHAR(20) NOT NULL,
    advice          TEXT NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_prescriptions_visit (visit_id),
    UNIQUE KEY uq_prescriptions_no (prescription_no),
    KEY idx_prescriptions_patient (patient_id),
    CONSTRAINT fk_rx_visit   FOREIGN KEY (visit_id)   REFERENCES visits (id),
    CONSTRAINT fk_rx_patient FOREIGN KEY (patient_id) REFERENCES patients (id),
    CONSTRAINT fk_rx_doctor  FOREIGN KEY (doctor_id)  REFERENCES doctors (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE prescription_items (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    prescription_id INT UNSIGNED NOT NULL,
    sort_order      SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    medicine_name   VARCHAR(150) NOT NULL,
    dosage          VARCHAR(80)  NOT NULL,
    frequency       VARCHAR(80)  NOT NULL,
    duration        VARCHAR(60)  NOT NULL,
    instructions    VARCHAR(255) NULL,
    PRIMARY KEY (id),
    KEY idx_rx_items_rx (prescription_id),
    CONSTRAINT fk_rxitem_rx FOREIGN KEY (prescription_id) REFERENCES prescriptions (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE follow_ups (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    patient_id     INT UNSIGNED NOT NULL,
    visit_id       INT UNSIGNED NULL,
    doctor_id      INT UNSIGNED NOT NULL,
    follow_up_date DATE NOT NULL,
    notes          VARCHAR(500) NULL,
    status         ENUM('Pending','Completed','Cancelled') NOT NULL DEFAULT 'Pending',
    completed_at   DATETIME NULL,
    created_by     INT UNSIGNED NOT NULL,
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_followups_date (follow_up_date, status),
    KEY idx_followups_patient (patient_id),
    KEY idx_followups_doctor (doctor_id),
    CONSTRAINT fk_fu_patient FOREIGN KEY (patient_id) REFERENCES patients (id),
    CONSTRAINT fk_fu_visit   FOREIGN KEY (visit_id)   REFERENCES visits (id),
    CONSTRAINT fk_fu_doctor  FOREIGN KEY (doctor_id)  REFERENCES doctors (id),
    CONSTRAINT fk_fu_creator FOREIGN KEY (created_by) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Services, invoices and payments (all money columns are DECIMAL, never FLOAT)
-- ---------------------------------------------------------------------
CREATE TABLE services (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    clinic_id   INT UNSIGNED NOT NULL DEFAULT 1,
    name        VARCHAR(120) NOT NULL,
    description VARCHAR(255) NULL,
    price       DECIMAL(10,2) NOT NULL,
    is_active   TINYINT(1) NOT NULL DEFAULT 1,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_services_name (clinic_id, name),
    CONSTRAINT fk_services_clinic FOREIGN KEY (clinic_id) REFERENCES clinics (id),
    CONSTRAINT chk_services_price CHECK (price >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- amount_paid  = payments received minus refunds
-- adjusted_amount = write-offs/discounts granted after issue
-- balance      = total - amount_paid - adjusted_amount (always recalculated by the server inside a transaction)
CREATE TABLE invoices (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    clinic_id       INT UNSIGNED NOT NULL DEFAULT 1,
    invoice_no      VARCHAR(30) NOT NULL,
    patient_id      INT UNSIGNED NOT NULL,
    visit_id        INT UNSIGNED NULL,
    issue_date      DATE NOT NULL,
    subtotal        DECIMAL(12,2) NOT NULL,
    discount_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    tax_rate        DECIMAL(5,2)  NOT NULL DEFAULT 0.00,
    tax_amount      DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    total           DECIMAL(12,2) NOT NULL,
    amount_paid     DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    adjusted_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    balance         DECIMAL(12,2) NOT NULL,
    status          ENUM('Unpaid','Partial','Paid','Void') NOT NULL DEFAULT 'Unpaid',
    notes           VARCHAR(500) NULL,
    created_by      INT UNSIGNED NOT NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    voided_at       DATETIME NULL,
    voided_by       INT UNSIGNED NULL,
    void_reason     VARCHAR(255) NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_invoices_no (invoice_no),
    KEY idx_invoices_patient (patient_id),
    KEY idx_invoices_visit (visit_id),
    KEY idx_invoices_date (issue_date, status),
    CONSTRAINT fk_inv_clinic  FOREIGN KEY (clinic_id)  REFERENCES clinics (id),
    CONSTRAINT fk_inv_patient FOREIGN KEY (patient_id) REFERENCES patients (id),
    CONSTRAINT fk_inv_visit   FOREIGN KEY (visit_id)   REFERENCES visits (id),
    CONSTRAINT fk_inv_creator FOREIGN KEY (created_by) REFERENCES users (id),
    CONSTRAINT fk_inv_voider  FOREIGN KEY (voided_by)  REFERENCES users (id),
    CONSTRAINT chk_inv_amounts CHECK (subtotal >= 0 AND discount_amount >= 0 AND total >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE invoice_items (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    invoice_id  INT UNSIGNED NOT NULL,
    item_type   ENUM('Consultation','Service') NOT NULL,
    service_id  INT UNSIGNED NULL,
    description VARCHAR(200) NOT NULL,
    quantity    SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    unit_price  DECIMAL(10,2) NOT NULL,
    line_total  DECIMAL(12,2) NOT NULL,
    PRIMARY KEY (id),
    KEY idx_items_invoice (invoice_id),
    CONSTRAINT fk_items_invoice FOREIGN KEY (invoice_id) REFERENCES invoices (id) ON DELETE CASCADE,
    CONSTRAINT fk_items_service FOREIGN KEY (service_id) REFERENCES services (id),
    CONSTRAINT chk_items_qty    CHECK (quantity >= 1),
    CONSTRAINT chk_items_price  CHECK (unit_price >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Append-only ledger: a mistake is corrected by a new Refund/Adjustment row, never by editing or deleting.
CREATE TABLE payments (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    invoice_id  INT UNSIGNED NOT NULL,
    receipt_no  VARCHAR(30) NOT NULL,
    entry_type  ENUM('Payment','Refund','Adjustment') NOT NULL DEFAULT 'Payment',
    amount      DECIMAL(12,2) NOT NULL,
    method      ENUM('Cash','Card','Bank Transfer','Mobile Wallet') NULL,
    reference   VARCHAR(100) NULL,
    reason      VARCHAR(255) NULL,
    paid_at     DATETIME NOT NULL,
    recorded_by INT UNSIGNED NOT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_payments_receipt (receipt_no),
    KEY idx_payments_invoice (invoice_id),
    KEY idx_payments_paid_at (paid_at),
    CONSTRAINT fk_pay_invoice FOREIGN KEY (invoice_id)  REFERENCES invoices (id),
    CONSTRAINT fk_pay_user    FOREIGN KEY (recorded_by) REFERENCES users (id),
    CONSTRAINT chk_pay_amount CHECK (amount > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Audit trail (append-only)
-- ---------------------------------------------------------------------
CREATE TABLE audit_logs (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id     INT UNSIGNED NULL,
    action      VARCHAR(60)  NOT NULL,
    entity_type VARCHAR(40)  NOT NULL,
    entity_id   BIGINT UNSIGNED NULL,
    summary     VARCHAR(255) NOT NULL,
    details     LONGTEXT NULL,
    ip_address  VARCHAR(45)  NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_audit_created (created_at),
    KEY idx_audit_entity (entity_type, entity_id),
    KEY idx_audit_user (user_id),
    CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Append-only protection enforced by the database itself.
-- Even a bug (or an admin with a SQL tool through the app) cannot silently rewrite history.
-- ---------------------------------------------------------------------
DELIMITER $$

CREATE TRIGGER trg_payments_no_update BEFORE UPDATE ON payments FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Payment records are append-only: record a refund or adjustment instead.';
END$$

CREATE TRIGGER trg_payments_no_delete BEFORE DELETE ON payments FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Payment records cannot be deleted.';
END$$

CREATE TRIGGER trg_audit_no_update BEFORE UPDATE ON audit_logs FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Audit log entries cannot be changed.';
END$$

CREATE TRIGGER trg_audit_no_delete BEFORE DELETE ON audit_logs FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Audit log entries cannot be deleted.';
END$$

CREATE TRIGGER trg_amendments_no_update BEFORE UPDATE ON visit_amendments FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Visit amendment history cannot be changed.';
END$$

CREATE TRIGGER trg_amendments_no_delete BEFORE DELETE ON visit_amendments FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Visit amendment history cannot be deleted.';
END$$

CREATE TRIGGER trg_visits_no_delete_final BEFORE DELETE ON visits FOR EACH ROW
BEGIN
    IF OLD.status = 'Finalized' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Finalized visits cannot be deleted.';
    END IF;
END$$

CREATE TRIGGER trg_invoices_no_delete BEFORE DELETE ON invoices FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Invoices cannot be deleted: void them instead.';
END$$

DELIMITER ;

-- =====================================================================
-- Reference data (fixed application data, not demo data)
-- =====================================================================
INSERT INTO clinics (id, name) VALUES (1, 'Clinic CRM');

INSERT INTO roles (code, name, description) VALUES
 ('admin',        'Clinic Admin', 'Manages staff, doctors, services, settings and oversees operations and billing.'),
 ('doctor',       'Doctor',       'Sees own appointments and queue, records consultations, prescriptions and follow-ups.'),
 ('receptionist', 'Receptionist', 'Registers patients, books appointments, manages the queue and walk-ins.'),
 ('accountant',   'Accountant',   'Manages invoices, payments, refunds and financial reports.'),
 ('patient',      'Patient',      'Reserved for the future patient portal. Cannot sign in to the staff application.');

INSERT INTO permissions (code, description) VALUES
 ('dashboard.view',        'Open the role dashboard'),
 ('users.manage',          'Create, edit and activate/deactivate staff accounts'),
 ('specialties.manage',    'Manage medical specialties'),
 ('doctors.view',          'View doctor profiles and schedules'),
 ('doctors.manage',        'Create and edit doctor profiles and schedules'),
 ('services.manage',       'Manage clinic services and prices'),
 ('settings.manage',       'Change clinic settings'),
 ('patients.view',         'View patient demographics and contact details'),
 ('patients.view_billing', 'View only the patient details needed for billing'),
 ('patients.create',       'Register new patients'),
 ('patients.update',       'Edit patient demographics'),
 ('patients.archive',      'Archive and restore patients'),
 ('appointments.view',     'View appointments'),
 ('appointments.manage',   'Book, reschedule, cancel appointments and run the queue'),
 ('appointments.consult',  'Start a consultation from the queue'),
 ('queue.view',            'View the waiting queue'),
 ('visits.view',           'View visits, clinical history and prescriptions'),
 ('visits.write',          'Record consultations and prescriptions'),
 ('visits.amend',          'Amend finalized consultations (history is kept)'),
 ('followups.view',        'View follow-ups'),
 ('followups.create',      'Create follow-ups'),
 ('followups.update',      'Complete or cancel follow-ups'),
 ('invoices.view',         'View invoices'),
 ('invoices.create',       'Create invoices'),
 ('invoices.void',         'Void invoices'),
 ('payments.view',         'View the payments ledger'),
 ('payments.record',       'Record payments'),
 ('payments.refund',       'Record refunds'),
 ('payments.adjust',       'Record write-offs/adjustments'),
 ('reports.financial',     'View financial reports'),
 ('reports.operational',   'View operational reports'),
 ('audit.view',            'Review the audit log'),
 ('exports.patients',      'Export patient list as CSV'),
 ('exports.appointments',  'Export appointments as CSV'),
 ('exports.financial',     'Export invoices and payments as CSV');

-- The role -> permission matrix (documented in docs/security.md)
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p ON
    (r.code = 'admin' AND p.code IN (
        'dashboard.view','users.manage','specialties.manage','doctors.view','doctors.manage','services.manage','settings.manage',
        'patients.view','patients.create','patients.update','patients.archive',
        'appointments.view','appointments.manage','queue.view','followups.view','followups.update',
        'invoices.view','payments.view','reports.financial','reports.operational','audit.view',
        'exports.patients','exports.appointments','exports.financial'))
 OR (r.code = 'doctor' AND p.code IN (
        'dashboard.view','patients.view','appointments.view','appointments.consult','queue.view',
        'visits.view','visits.write','visits.amend','followups.view','followups.create','followups.update'))
 OR (r.code = 'receptionist' AND p.code IN (
        'dashboard.view','doctors.view','patients.view','patients.create','patients.update',
        'appointments.view','appointments.manage','queue.view','followups.view','followups.update',
        'invoices.view','invoices.create','payments.record'))
 OR (r.code = 'accountant' AND p.code IN (
        'dashboard.view','patients.view_billing','invoices.view','invoices.create','invoices.void',
        'payments.view','payments.record','payments.refund','payments.adjust','reports.financial','exports.financial'));

INSERT INTO number_sequences (name, last_value) VALUES ('patient', 0), ('invoice', 0), ('receipt', 0), ('prescription', 0);

INSERT INTO settings (clinic_id, setting_key, setting_value) VALUES
 (1, 'clinic_name',         'Clinic CRM'),
 (1, 'clinic_address',      ''),
 (1, 'clinic_phone',        ''),
 (1, 'clinic_email',        ''),
 (1, 'currency_symbol',     'Rs.'),
 (1, 'currency_code',       'PKR'),
 (1, 'opening_time',        '09:00'),
 (1, 'closing_time',        '21:00'),
 (1, 'tax_rate_percent',    '0.00'),
 (1, 'invoice_footer',      'Thank you for choosing our clinic. This is a computer-generated document.');
