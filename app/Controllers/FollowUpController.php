<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\BusinessRuleException;
use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Response;
use App\Models\FollowUp;
use App\Models\Patient;
use App\Services\AccessPolicy;
use App\Services\FollowUpService;

final class FollowUpController extends Controller
{
    private const BUCKETS = ['overdue' => 'Overdue', 'due' => 'Due today', 'upcoming' => 'Upcoming', 'completed' => 'Completed', 'cancelled' => 'Cancelled', 'all' => 'All'];

    public function index(): Response
    {
        $scope = AccessPolicy::doctorScope();
        $counts = FollowUp::counts($scope);
        $from = $this->dateOrEmpty('from');
        $to = $this->dateOrEmpty('to');
        $bucket = $this->request->string('bucket');
        if (!isset(self::BUCKETS[$bucket])) {
            $bucket = $counts['overdue'] > 0 ? 'overdue' : ($counts['due'] > 0 ? 'due' : 'upcoming');
        }
        $page = FollowUp::paginate(['bucket' => $bucket, 'doctor_id' => $scope, 'from' => $from, 'to' => $to], max(1, $this->request->int('page', 1)), 15);

        return $this->view('followups/index', [
            'title' => 'Follow-ups', 'page' => $page, 'bucket' => $bucket, 'buckets' => self::BUCKETS, 'counts' => $counts, 'from' => $from, 'to' => $to,
        ]);
    }

    private function dateOrEmpty(string $key): string
    {
        $value = $this->request->string($key);

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 ? $value : '';
    }

    public function create(): Response
    {
        $id = $this->request->int('patient_id');

        return $this->view('followups/form', ['title' => 'New follow-up', 'patient' => $id > 0 ? Patient::find($id) : null]);
    }

    public function store(): Response
    {
        $doctorId = Auth::doctorId() ?? throw new HttpException(403);
        $patientId = $this->request->int('patient_id');
        $v = FollowUpService::validate($this->request->body());
        $errors = $v->errors();
        if ($patientId === 0) {
            $errors['patient_id'] = 'Please choose a patient.';
        } elseif (!AccessPolicy::canViewClinical($patientId)) {
            throw new HttpException(403);
        }
        if ($errors !== []) {
            return $this->backWithErrors('/followups/create', $errors);
        }

        $d = $v->validated();
        FollowUpService::create($patientId, null, $doctorId, $d['follow_up_date'], $d['notes'], (int) Auth::id());

        return $this->redirectWith('/followups', 'success', 'Follow-up added.');
    }

    public function status(int $id): Response
    {
        $followUp = FollowUp::find($id) ?? throw new HttpException(404);
        $scope = AccessPolicy::doctorScope();
        if ($scope !== null && (int) $followUp['doctor_id'] !== $scope) {
            throw new HttpException(403);
        }
        try {
            FollowUpService::setStatus($id, $this->request->string('to'));
        } catch (BusinessRuleException $e) {
            return $this->redirectWith('/followups', 'error', $e->getMessage());
        }

        return $this->redirectWith('/followups', 'success', 'Follow-up marked ' . strtolower($this->request->string('to')) . '.');
    }
}
