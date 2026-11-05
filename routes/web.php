<?php
declare(strict_types=1);

/*
 * The route table: URL -> controller method -> permission needed.
 * A route without ->can(...) / ->any() / ->public() is denied (deny by default).
 */

use App\Controllers\AppointmentController;
use App\Controllers\AuditController;
use App\Controllers\AuthController;
use App\Controllers\DashboardController;
use App\Controllers\DoctorController;
use App\Controllers\FollowUpController;
use App\Controllers\InvoiceController;
use App\Controllers\PatientController;
use App\Controllers\PaymentController;
use App\Controllers\QueueController;
use App\Controllers\ReportController;
use App\Controllers\ServiceController;
use App\Controllers\SettingsController;
use App\Controllers\SpecialtyController;
use App\Controllers\UserController;
use App\Controllers\VisitController;
use App\Core\Router;

return static function (Router $r): void {
    // --- Authentication ---
    $r->get('/', [AuthController::class, 'home'])->public();
    $r->get('/login', [AuthController::class, 'showLogin'])->public();
    $r->post('/login', [AuthController::class, 'login'])->public();
    $r->post('/logout', [AuthController::class, 'logout'])->any();
    $r->get('/account/password', [AuthController::class, 'showPassword'])->any();
    $r->post('/account/password', [AuthController::class, 'changePassword'])->any();

    $r->get('/dashboard', [DashboardController::class, 'index'])->can('dashboard.view');

    // --- Administration ---
    $r->get('/users', [UserController::class, 'index'])->can('users.manage');
    $r->get('/users/create', [UserController::class, 'create'])->can('users.manage');
    $r->post('/users', [UserController::class, 'store'])->can('users.manage');
    $r->get('/users/{id}/edit', [UserController::class, 'edit'])->can('users.manage');
    $r->post('/users/{id}', [UserController::class, 'update'])->can('users.manage');
    $r->post('/users/{id}/toggle', [UserController::class, 'toggle'])->can('users.manage');
    $r->post('/users/{id}/reset-password', [UserController::class, 'resetPassword'])->can('users.manage');

    $r->get('/specialties', [SpecialtyController::class, 'index'])->can('specialties.manage');
    $r->get('/specialties/create', [SpecialtyController::class, 'create'])->can('specialties.manage');
    $r->post('/specialties', [SpecialtyController::class, 'store'])->can('specialties.manage');
    $r->get('/specialties/{id}/edit', [SpecialtyController::class, 'edit'])->can('specialties.manage');
    $r->post('/specialties/{id}', [SpecialtyController::class, 'update'])->can('specialties.manage');
    $r->post('/specialties/{id}/toggle', [SpecialtyController::class, 'toggle'])->can('specialties.manage');

    $r->get('/services', [ServiceController::class, 'index'])->can('services.manage');
    $r->get('/services/create', [ServiceController::class, 'create'])->can('services.manage');
    $r->post('/services', [ServiceController::class, 'store'])->can('services.manage');
    $r->get('/services/{id}/edit', [ServiceController::class, 'edit'])->can('services.manage');
    $r->post('/services/{id}', [ServiceController::class, 'update'])->can('services.manage');
    $r->post('/services/{id}/toggle', [ServiceController::class, 'toggle'])->can('services.manage');

    $r->get('/settings', [SettingsController::class, 'edit'])->can('settings.manage');
    $r->post('/settings', [SettingsController::class, 'update'])->can('settings.manage');
    $r->get('/audit', [AuditController::class, 'index'])->can('audit.view');

    // --- Doctors ---
    $r->get('/doctors', [DoctorController::class, 'index'])->can('doctors.view');
    $r->get('/doctors/create', [DoctorController::class, 'create'])->can('doctors.manage');
    $r->post('/doctors', [DoctorController::class, 'store'])->can('doctors.manage');
    $r->get('/doctors/{id}', [DoctorController::class, 'show'])->can('doctors.view');
    $r->get('/doctors/{id}/edit', [DoctorController::class, 'edit'])->can('doctors.manage');
    $r->post('/doctors/{id}', [DoctorController::class, 'update'])->can('doctors.manage');
    $r->post('/doctors/{id}/toggle', [DoctorController::class, 'toggle'])->can('doctors.manage');
    $r->post('/doctors/{id}/schedule', [DoctorController::class, 'addSchedule'])->can('doctors.manage');
    $r->post('/doctors/{id}/schedule/{sid}/delete', [DoctorController::class, 'removeSchedule'])->can('doctors.manage');

    // --- Patients ---
    $r->get('/patients', [PatientController::class, 'index'])->can(['patients.view', 'patients.view_billing']);
    $r->get('/patients/lookup', [PatientController::class, 'lookup'])->can(['patients.view', 'patients.view_billing']);
    $r->get('/patients/export', [PatientController::class, 'export'])->can('exports.patients');
    $r->get('/patients/create', [PatientController::class, 'create'])->can('patients.create');
    $r->post('/patients', [PatientController::class, 'store'])->can('patients.create');
    $r->get('/patients/{id}', [PatientController::class, 'show'])->can(['patients.view', 'patients.view_billing']);
    $r->get('/patients/{id}/edit', [PatientController::class, 'edit'])->can('patients.update');
    $r->post('/patients/{id}', [PatientController::class, 'update'])->can('patients.update');
    $r->post('/patients/{id}/archive', [PatientController::class, 'archive'])->can('patients.archive');
    $r->post('/patients/{id}/restore', [PatientController::class, 'restore'])->can('patients.archive');

    // --- Appointments and queue ---
    $r->get('/appointments', [AppointmentController::class, 'index'])->can('appointments.view');
    $r->get('/appointments/create', [AppointmentController::class, 'create'])->can('appointments.manage');
    $r->post('/appointments', [AppointmentController::class, 'store'])->can('appointments.manage');
    $r->get('/appointments/slots', [AppointmentController::class, 'slots'])->can('appointments.manage');
    $r->get('/appointments/walk-in', [AppointmentController::class, 'walkIn'])->can('appointments.manage');
    $r->post('/appointments/walk-in', [AppointmentController::class, 'storeWalkIn'])->can('appointments.manage');
    $r->get('/appointments/export', [AppointmentController::class, 'export'])->can('exports.appointments');
    $r->get('/appointments/{id}', [AppointmentController::class, 'show'])->can('appointments.view');
    $r->get('/appointments/{id}/reschedule', [AppointmentController::class, 'reschedule'])->can('appointments.manage');
    $r->post('/appointments/{id}/reschedule', [AppointmentController::class, 'storeReschedule'])->can('appointments.manage');
    $r->post('/appointments/{id}/status', [AppointmentController::class, 'status'])->can('appointments.manage');
    $r->post('/appointments/{id}/consult', [VisitController::class, 'start'])->can('appointments.consult');
    $r->get('/queue', [QueueController::class, 'index'])->can('queue.view');

    // --- Consultations ---
    $r->get('/visits/{id}', [VisitController::class, 'show'])->can('visits.view');
    $r->get('/visits/{id}/edit', [VisitController::class, 'edit'])->can('visits.write');
    $r->post('/visits/{id}', [VisitController::class, 'save'])->can('visits.write');
    $r->get('/visits/{id}/amend', [VisitController::class, 'amend'])->can('visits.amend');
    $r->post('/visits/{id}/amend', [VisitController::class, 'storeAmendment'])->can('visits.amend');
    $r->get('/visits/{id}/prescription', [VisitController::class, 'prescription'])->can('visits.view');

    // --- Follow-ups ---
    $r->get('/followups', [FollowUpController::class, 'index'])->can('followups.view');
    $r->get('/followups/create', [FollowUpController::class, 'create'])->can('followups.create');
    $r->post('/followups', [FollowUpController::class, 'store'])->can('followups.create');
    $r->post('/followups/{id}/status', [FollowUpController::class, 'status'])->can('followups.update');

    // --- Billing ---
    $r->get('/invoices', [InvoiceController::class, 'index'])->can('invoices.view');
    $r->get('/invoices/create', [InvoiceController::class, 'create'])->can('invoices.create');
    $r->post('/invoices', [InvoiceController::class, 'store'])->can('invoices.create');
    $r->get('/invoices/export', [InvoiceController::class, 'export'])->can('exports.financial');
    $r->get('/invoices/{id}', [InvoiceController::class, 'show'])->can('invoices.view');
    $r->get('/invoices/{id}/print', [InvoiceController::class, 'print'])->can('invoices.view');
    $r->post('/invoices/{id}/payments', [InvoiceController::class, 'recordPayment'])->can('payments.record');
    $r->post('/invoices/{id}/refund', [InvoiceController::class, 'refund'])->can('payments.refund');
    $r->post('/invoices/{id}/adjust', [InvoiceController::class, 'adjust'])->can('payments.adjust');
    $r->post('/invoices/{id}/void', [InvoiceController::class, 'void'])->can('invoices.void');
    $r->get('/payments', [PaymentController::class, 'index'])->can('payments.view');
    $r->get('/payments/export', [PaymentController::class, 'export'])->can('exports.financial');
    $r->get('/payments/{id}/receipt', [PaymentController::class, 'receipt'])->can(['payments.view', 'invoices.view']);

    // --- Reports ---
    $r->get('/reports/financial', [ReportController::class, 'financial'])->can('reports.financial');
    $r->get('/reports/operational', [ReportController::class, 'operational'])->can('reports.operational');
};
