<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Config\Roles;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\Response;
use App\Services\DashboardService;
use App\Services\DateRange;

final class DashboardController extends Controller
{
    public function index(): Response
    {
        $range = DateRange::fromInput(
            $this->request->string('preset'),
            $this->request->string('from'),
            $this->request->string('to')
        );

        return match (Auth::role()) {
            Roles::ADMIN        => $this->view('dashboard/admin', ['title' => 'Dashboard', 'charts' => true, 'range' => $range] + DashboardService::admin($range)),
            Roles::ACCOUNTANT   => $this->view('dashboard/accountant', ['title' => 'Dashboard', 'charts' => true, 'range' => $range] + DashboardService::accountant($range)),
            Roles::RECEPTIONIST => $this->view('dashboard/receptionist', ['title' => 'Dashboard', 'charts' => true] + DashboardService::receptionist()),
            Roles::DOCTOR       => $this->view('dashboard/doctor', ['title' => 'Dashboard', 'charts' => true, 'hasProfile' => Auth::doctorId() !== null]
                + (Auth::doctorId() !== null ? DashboardService::doctor((int) Auth::doctorId()) : [])),
            default             => $this->forbidden(),
        };
    }
}
