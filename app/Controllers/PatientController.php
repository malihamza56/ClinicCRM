<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Config\Permissions;
use App\Core\Auth;
use App\Core\BusinessRuleException;
use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Response;
use App\Core\Session;
use App\Models\Appointment;
use App\Models\FollowUp;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\Visit;
use App\Services\AccessPolicy;
use App\Services\AuditService;
use App\Services\ExportService;
use App\Services\PatientService;

final class PatientController extends Controller
{
    public function index(): Response
    {
        $filters = ['q' => $this->request->string('q'), 'status' => $this->request->string('status') ?: 'active'];
        $page = Patient::paginate($filters, max(1, $this->request->int('page', 1)), 15, AccessPolicy::doctorScope());

        return $this->view('patients/index', ['title' => 'Patients', 'page' => $page, 'filters' => $filters, 'fullView' => Auth::can(Permissions::PATIENTS_VIEW)]);
    }

    /** JSON for the patient search box. Doctors only find their own patients. */
    public function lookup(): Response
    {
        $q = $this->request->string('q');
        if (mb_strlen($q) < 2) {
            return $this->json([]);
        }
        $rows = Patient::lookup($q, 8, AccessPolicy::doctorScope());

        return $this->json(array_map(static fn (array $r): array => [
            'id' => (int) $r['id'], 'full_name' => $r['full_name'], 'patient_code' => $r['patient_code'], 'phone' => $r['phone'],
        ], $rows));
    }

    public function create(): Response
    {
        return $this->view('patients/form', ['title' => 'Register patient', 'patient' => null, 'next' => $this->safeNext(), 'duplicateId' => Session::flashed('duplicate_id')]);
    }

    public function store(): Response
    {
        $v = PatientService::validate($this->request->body());
        $next = $this->safeNext();
        $back = '/patients/create' . ($next ? '?next=' . $next : '');
        if ($v->fails()) {
            return $this->backWithErrors($back, $v->errors());
        }

        try {
            $created = PatientService::register($v->validated(), (int) Auth::id());
        } catch (BusinessRuleException $e) {
            Session::flash('duplicate_id', $e->context['duplicate_id'] ?? null);

            return $this->backWithErrors($back, ['first_name' => $e->getMessage()]);
        }

        $target = match ($next) {
            'book'    => '/appointments/create?patient_id=' . $created['id'],
            'walk-in' => '/appointments/walk-in?patient_id=' . $created['id'],
            default   => '/patients/' . $created['id'],
        };

        return $this->redirectWith($target, 'success', 'Patient registered with ID ' . $created['code'] . '.');
    }

    public function show(int $id): Response
    {
        $patient = Patient::find($id) ?? throw new HttpException(404);
        $full = Auth::can(Permissions::PATIENTS_VIEW);
        if ($full) {
            AccessPolicy::requirePatientDemographics($id);
        }

        $data = ['title' => $patient['full_name'], 'patient' => $patient, 'full' => $full];
        $data['appointments'] = $full ? Appointment::forPatient($id, 8) : [];
        $data['invoices'] = Auth::can(Permissions::INVOICES_VIEW) ? Invoice::forPatient($id) : [];
        $data['followUps'] = $full && Auth::can(Permissions::FOLLOWUPS_VIEW) ? FollowUp::forPatient($id) : [];
        $data['canClinical'] = AccessPolicy::canViewClinical($id);
        $data['visits'] = $data['canClinical'] ? Visit::forPatient($id) : [];

        return $this->view('patients/show', $data);
    }

    public function edit(int $id): Response
    {
        $patient = Patient::find($id) ?? throw new HttpException(404);

        return $this->view('patients/form', ['title' => 'Edit patient', 'patient' => $patient, 'next' => null, 'duplicateId' => Session::flashed('duplicate_id')]);
    }

    public function update(int $id): Response
    {
        Patient::find($id) ?? throw new HttpException(404);
        $v = PatientService::validate($this->request->body());
        if ($v->fails()) {
            return $this->backWithErrors("/patients/$id/edit", $v->errors());
        }
        try {
            PatientService::update($id, $v->validated());
        } catch (BusinessRuleException $e) {
            Session::flash('duplicate_id', $e->context['duplicate_id'] ?? null);

            return $this->backWithErrors("/patients/$id/edit", ['first_name' => $e->getMessage()]);
        }

        return $this->redirectWith("/patients/$id", 'success', 'Patient details updated.');
    }

    public function archive(int $id): Response
    {
        Patient::find($id) ?? throw new HttpException(404);
        try {
            PatientService::archive($id, (int) Auth::id());
        } catch (BusinessRuleException $e) {
            return $this->redirectWith("/patients/$id", 'error', $e->getMessage());
        }

        return $this->redirectWith("/patients/$id", 'success', 'Patient archived. Their history is kept and they can be restored at any time.');
    }

    public function restore(int $id): Response
    {
        Patient::find($id) ?? throw new HttpException(404);
        PatientService::restore($id, (int) Auth::id());

        return $this->redirectWith("/patients/$id", 'success', 'Patient restored.');
    }

    public function export(): Response
    {
        $filters = ['q' => $this->request->string('q'), 'status' => $this->request->string('status') ?: 'active'];
        [$headings, $rows] = ExportService::dataset('patients', $filters);
        AuditService::log('export.csv', 'patient', null, 'Exported ' . count($rows) . ' patients to CSV');

        return Response::download('patients-' . date('Y-m-d') . '.csv', ExportService::toCsv($headings, $rows), 'text/csv; charset=UTF-8');
    }

    private function safeNext(): ?string
    {
        $next = $this->request->string('next');

        return in_array($next, ['book', 'walk-in'], true) ? $next : null;
    }
}
