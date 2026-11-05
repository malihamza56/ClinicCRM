<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Config\AppointmentStatus;
use App\Core\Auth;
use App\Core\BusinessRuleException;
use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Response;
use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\Visit;
use App\Services\AccessPolicy;
use App\Services\AppointmentService;
use App\Services\AuditService;
use App\Services\ExportService;

final class AppointmentController extends Controller
{
    public function index(): Response
    {
        $filters = $this->filters();
        $page = Appointment::paginate($filters, max(1, $this->request->int('page', 1)), 15);

        return $this->view('appointments/index', [
            'title' => 'Appointments', 'page' => $page, 'filters' => $filters,
            'doctors' => AccessPolicy::doctorScope() === null ? Doctor::all() : [], 'statuses' => AppointmentStatus::ALL,
        ]);
    }

    public function create(): Response
    {
        return $this->view('appointments/form', [
            'title' => 'Book appointment', 'doctors' => Doctor::all(true), 'patient' => $this->patientFromQuery(),
            'appointment' => null, 'date' => date('Y-m-d'),
        ]);
    }

    public function store(): Response
    {
        $v = AppointmentService::validateBooking($this->request->body());
        if ($v->fails()) {
            return $this->backWithErrors('/appointments/create', $v->errors());
        }
        $d = $v->validated();
        try {
            $id = AppointmentService::book((int) $d['patient_id'], (int) $d['doctor_id'], $d['appointment_date'], $d['start_time'], $d['reason'], (int) Auth::id());
        } catch (BusinessRuleException $e) {
            return $this->backWithErrors('/appointments/create', ['start_time' => $e->getMessage()]);
        }

        return $this->redirectWith("/appointments/$id", 'success', 'Appointment booked.');
    }

    /** JSON list of free time slots for the booking form. */
    public function slots(): Response
    {
        $doctorId = $this->request->int('doctor_id');
        $date = $this->request->string('date');
        if ($doctorId === 0 || preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1) {
            return $this->json(['slots' => []]);
        }
        $slots = array_map(
            static fn (array $s): array => $s + ['label' => format_time($s['start'])],
            AppointmentService::slots($doctorId, $date, $this->request->int('exclude') ?: null)
        );

        return $this->json(['slots' => $slots]);
    }

    public function walkIn(): Response
    {
        return $this->view('appointments/walk_in', ['title' => 'Register walk-in', 'doctors' => Doctor::all(true), 'patient' => $this->patientFromQuery()]);
    }

    public function storeWalkIn(): Response
    {
        $patientId = $this->request->int('patient_id');
        $doctorId = $this->request->int('doctor_id');
        $reason = $this->request->string('reason');
        $errors = [];
        if ($patientId === 0) {
            $errors['patient_id'] = 'Please choose a patient.';
        }
        if ($doctorId === 0) {
            $errors['doctor_id'] = 'Please choose a doctor.';
        }
        if (mb_strlen($reason) > 255) {
            $errors['reason'] = 'Reason must not be longer than 255 characters.';
        }
        if ($errors !== []) {
            return $this->backWithErrors('/appointments/walk-in', $errors);
        }
        try {
            $id = AppointmentService::registerWalkIn($patientId, $doctorId, $reason !== '' ? $reason : null, (int) Auth::id());
        } catch (BusinessRuleException $e) {
            return $this->backWithErrors('/appointments/walk-in', ['doctor_id' => $e->getMessage()]);
        }

        return $this->redirectWith('/queue', 'success', 'Walk-in registered and added to the queue.');
    }

    public function show(int $id): Response
    {
        $appointment = Appointment::find($id) ?? throw new HttpException(404);
        AccessPolicy::requireAppointmentAccess($appointment);
        $visit = Visit::findByAppointment($id);

        return $this->view('appointments/show', [
            'title' => 'Appointment', 'a' => $appointment, 'visit' => $visit,
            'canOpenVisit' => $visit !== null && AccessPolicy::canViewClinical((int) $appointment['patient_id']),
            'next' => AppointmentStatus::nextStatuses((string) $appointment['status']),
            'canReschedule' => $appointment['booking_type'] === 'Scheduled' && AppointmentStatus::canReschedule((string) $appointment['status']),
        ]);
    }

    public function reschedule(int $id): Response
    {
        $appointment = Appointment::find($id) ?? throw new HttpException(404);
        if (!AppointmentStatus::canReschedule((string) $appointment['status']) || $appointment['booking_type'] !== 'Scheduled') {
            return $this->redirectWith("/appointments/$id", 'error', 'Only scheduled or confirmed appointments can be rescheduled.');
        }

        return $this->view('appointments/reschedule', ['title' => 'Reschedule appointment', 'a' => $appointment]);
    }

    public function storeReschedule(int $id): Response
    {
        Appointment::find($id) ?? throw new HttpException(404);
        $date = $this->request->string('appointment_date');
        $start = $this->request->string('start_time');
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1 || preg_match('/^\d{2}:\d{2}$/', $start) !== 1) {
            return $this->backWithErrors("/appointments/$id/reschedule", ['start_time' => 'Please choose a date and a free time.']);
        }
        try {
            AppointmentService::reschedule($id, $date, $start, (int) Auth::id());
        } catch (BusinessRuleException $e) {
            return $this->backWithErrors("/appointments/$id/reschedule", ['start_time' => $e->getMessage()]);
        }

        return $this->redirectWith("/appointments/$id", 'success', 'Appointment rescheduled.');
    }

    public function status(int $id): Response
    {
        $appointment = Appointment::find($id) ?? throw new HttpException(404);
        $back = $this->request->string('back') === 'queue' ? '/queue' : "/appointments/$id";
        try {
            AppointmentService::changeStatus($id, $this->request->string('to'), (int) Auth::id(), $this->request->string('reason') ?: null);
        } catch (BusinessRuleException $e) {
            return $this->redirectWith($back, 'error', $e->getMessage());
        }

        return $this->redirectWith($back, 'success', 'Appointment updated to ' . $this->request->string('to') . '.');
    }

    public function export(): Response
    {
        [$headings, $rows] = ExportService::dataset('appointments', $this->filters());
        AuditService::log('export.csv', 'appointment', null, 'Exported ' . count($rows) . ' appointments to CSV');

        return Response::download('appointments-' . date('Y-m-d') . '.csv', ExportService::toCsv($headings, $rows), 'text/csv; charset=UTF-8');
    }

    /** @return array<string,mixed> */
    private function filters(): array
    {
        $query = $this->request->query();
        $date = array_key_exists('date', $query) ? $this->request->string('date') : date('Y-m-d'); // default: today; empty = all dates
        $doctorScope = AccessPolicy::doctorScope();

        return [
            'date' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1 ? $date : '',
            'doctor_id' => $doctorScope ?? $this->request->int('doctor_id'),
            'status' => in_array($this->request->string('status'), AppointmentStatus::ALL, true) ? $this->request->string('status') : '',
            'q' => $this->request->string('q'),
        ];
    }

    private function patientFromQuery(): ?array
    {
        $id = $this->request->int('patient_id');

        return $id > 0 ? Patient::find($id) : null;
    }
}
