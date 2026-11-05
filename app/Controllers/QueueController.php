<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Response;
use App\Models\Appointment;
use App\Models\Doctor;
use App\Services\AccessPolicy;

/** Live queue for today: who is with the doctor, who is waiting, who is still expected. */
final class QueueController extends Controller
{
    public function index(): Response
    {
        $scope = AccessPolicy::doctorScope();
        $filterDoctor = $scope ?? ($this->request->int('doctor_id') ?: null);
        $rows = Appointment::forDay(date('Y-m-d'), $filterDoctor);
        $groups = ['In Progress' => [], 'Waiting' => [], 'Expected' => [], 'Done' => []];
        foreach ($rows as $a) {
            $key = match ($a['status']) {
                'In Progress' => 'In Progress',
                'Waiting'     => 'Waiting',
                'Scheduled', 'Confirmed' => 'Expected',
                default       => 'Done',
            };
            $groups[$key][] = $a;
        }
        usort($groups['Waiting'], static fn (array $x, array $y): int => (int) $x['queue_number'] <=> (int) $y['queue_number']);

        return $this->view('queue/index', ['title' => 'Queue', 'groups' => $groups, 'isDoctor' => $scope !== null, 'doctors' => $scope === null ? Doctor::all(true) : [], 'doctorId' => $filterDoctor]);
    }
}
