<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Response;
use App\Models\AuditLog;
use App\Models\User;

final class AuditController extends Controller
{
    public function index(): Response
    {
        $filters = [
            'from' => $this->request->string('from'), 'to' => $this->request->string('to'),
            'action' => $this->request->string('action'), 'entity_type' => $this->request->string('entity_type'),
            'user_id' => $this->request->int('user_id'),
        ];
        $page = AuditLog::paginate($filters, max(1, $this->request->int('page', 1)), 25);

        return $this->view('audit/index', [
            'title' => 'Audit log', 'page' => $page, 'filters' => $filters,
            'actions' => AuditLog::distinct('action'), 'entities' => AuditLog::distinct('entity_type'), 'users' => User::all(),
        ]);
    }
}
