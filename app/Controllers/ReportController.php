<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Response;
use App\Services\DateRange;
use App\Services\ReportService;

final class ReportController extends Controller
{
    public function financial(): Response
    {
        $range = $this->range();

        return $this->view('reports/financial', ['title' => 'Financial reports', 'charts' => true, 'range' => $range, 'r' => ReportService::financial($range)]);
    }

    public function operational(): Response
    {
        $range = $this->range();

        return $this->view('reports/operational', ['title' => 'Operational reports', 'charts' => true, 'range' => $range, 'r' => ReportService::operational($range)]);
    }

    private function range(): DateRange
    {
        return DateRange::fromInput($this->request->string('preset'), $this->request->string('from'), $this->request->string('to'));
    }
}
