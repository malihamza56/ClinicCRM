<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\BusinessRuleException;
use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Response;
use App\Core\Validator;
use App\Models\Prescription;
use App\Models\Visit;
use App\Services\AccessPolicy;
use App\Services\AuditService;
use App\Services\FollowUpService;
use App\Services\VisitService;

/** Consultations: start from the queue, write notes and a prescription, finalize, amend with history. */
final class VisitController extends Controller
{
    public function start(int $id): Response
    {
        $doctorId = Auth::doctorId() ?? throw new HttpException(403);
        try {
            $visitId = VisitService::start($id, $doctorId, (int) Auth::id());
        } catch (BusinessRuleException $e) {
            return $this->redirectWith('/queue', 'error', $e->getMessage());
        }

        return $this->redirect("/visits/$visitId/edit");
    }

    public function show(int $id): Response
    {
        $visit = $this->loadVisit($id);
        $prescription = Prescription::findByVisit($id);
        $isOwner = Auth::doctorId() !== null && (int) $visit['doctor_id'] === Auth::doctorId();
        AuditService::log('visit.viewed', 'visit', $id, 'Opened clinical record');

        return $this->view('visits/show', [
            'title' => 'Consultation', 'v' => $visit, 'prescription' => $prescription,
            'items' => $prescription ? Prescription::items((int) $prescription['id']) : [],
            'amendments' => Visit::amendments($id), 'isOwner' => $isOwner,
        ]);
    }

    public function edit(int $id): Response
    {
        $visit = $this->loadVisit($id);
        AccessPolicy::requireVisitOwner($visit);
        if ($visit['status'] !== 'Draft') {
            return $this->redirectWith("/visits/$id", 'info', 'This consultation is finalized. Use "Amend" to change it.');
        }

        return $this->view('visits/form', ['title' => 'Consultation'] + $this->formData($visit, false));
    }

    public function save(int $id): Response
    {
        $visit = $this->loadVisit($id);
        AccessPolicy::requireVisitOwner($visit);
        $finalize = $this->request->string('action') === 'finalize';

        [$clinical, $medicines, $errors] = $this->readClinicalInput($finalize);
        $followUp = ['date' => null, 'notes' => null];
        if ($finalize && $this->request->string('follow_up_date') !== '') {
            $fv = FollowUpService::validate(['follow_up_date' => $this->request->string('follow_up_date'), 'notes' => $this->request->string('follow_up_notes')]);
            $errors += $fv->errors();
            $followUp = ['date' => $fv->validated()['follow_up_date'], 'notes' => $fv->validated()['notes']];
        }
        if ($errors !== []) {
            return $this->backWithErrors("/visits/$id/edit", $errors);
        }

        try {
            VisitService::saveDraft($id, $clinical, $medicines, $followUp, $finalize, (int) Auth::id());
        } catch (BusinessRuleException $e) {
            return $this->redirectWith("/visits/$id", 'error', $e->getMessage());
        }

        return $finalize
            ? $this->redirectWith("/visits/$id", 'success', 'Consultation finalized. The appointment is now completed.')
            : $this->redirectWith("/visits/$id/edit", 'success', 'Draft saved.');
    }

    public function amend(int $id): Response
    {
        $visit = $this->loadVisit($id);
        AccessPolicy::requireVisitOwner($visit);
        if ($visit['status'] !== 'Finalized') {
            return $this->redirect("/visits/$id/edit");
        }

        return $this->view('visits/form', ['title' => 'Amend consultation'] + $this->formData($visit, true));
    }

    public function storeAmendment(int $id): Response
    {
        $visit = $this->loadVisit($id);
        AccessPolicy::requireVisitOwner($visit);

        [$clinical, $medicines, $errors] = $this->readClinicalInput(true);
        $reason = Validator::make($this->request->body(), ['amend_reason' => 'required|min:5|max:500'], ['amend_reason' => 'Reason for the amendment']);
        $errors += $reason->errors();
        if ($errors !== []) {
            return $this->backWithErrors("/visits/$id/amend", $errors);
        }

        try {
            VisitService::amend($id, $clinical, $medicines, $reason->validated()['amend_reason'], (int) Auth::id());
        } catch (BusinessRuleException $e) {
            return $this->backWithErrors("/visits/$id/amend", ['amend_reason' => $e->getMessage()]);
        }

        return $this->redirectWith("/visits/$id", 'success', 'Consultation amended. The previous version is kept in the history.');
    }

    public function prescription(int $id): Response
    {
        $visit = $this->loadVisit($id);
        $prescription = Prescription::findByVisit($id);
        if ($prescription === null) {
            return $this->redirectWith("/visits/$id", 'info', 'This consultation has no prescription.');
        }
        AuditService::log('prescription.printed', 'visit', $id, 'Opened prescription for printing');

        return $this->view('visits/prescription', [
            'title' => 'Prescription ' . $prescription['prescription_no'], 'v' => $visit,
            'rx' => $prescription, 'items' => Prescription::items((int) $prescription['id']),
        ], 'layouts/print');
    }

    /** Loads a visit and enforces that the viewer is a doctor with a care relationship to the patient. */
    private function loadVisit(int $id): array
    {
        $visit = Visit::find($id) ?? throw new HttpException(404);
        AccessPolicy::requireClinicalAccess((int) $visit['patient_id']);

        return $visit;
    }

    /** @return array{0:array<string,mixed>,1:array<int,array<string,?string>>,2:array<string,string>} */
    private function readClinicalInput(bool $strict): array
    {
        $v = VisitService::validateClinical($this->request->body(), $strict);
        $parsed = VisitService::parseMedicines($this->request->body());

        return [$v->validated(), $parsed['items'], $v->errors() + $parsed['errors']];
    }

    /** @return array<string,mixed> */
    private function formData(array $visit, bool $amending): array
    {
        $snapshot = VisitService::snapshot((int) $visit['id']);

        return ['v' => $visit, 'amending' => $amending, 'clinical' => $snapshot['clinical'], 'rx' => $snapshot['prescription']];
    }
}
