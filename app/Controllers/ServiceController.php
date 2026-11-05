<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Money;
use App\Core\Response;
use App\Core\Validator;
use App\Models\Service;
use App\Services\AuditService;

/** Billable services and their prices. Changing a price never alters invoices that were already issued. */
final class ServiceController extends Controller
{
    public function index(): Response
    {
        return $this->view('services/index', ['title' => 'Services & fees', 'services' => Service::all()]);
    }

    public function create(): Response
    {
        return $this->view('services/form', ['title' => 'New service', 'service' => null]);
    }

    public function store(): Response
    {
        $v = $this->validated();
        if ($v->fails()) {
            return $this->backWithErrors('/services/create', $v->errors());
        }
        $d = $v->validated();
        $id = Service::create($d['name'], $d['description'], Money::toDecimal((int) Money::parse($d['price'])));
        AuditService::log('service.created', 'service', $id, 'Created service ' . $d['name'], ['price' => $d['price']]);

        return $this->redirectWith('/services', 'success', 'Service added.');
    }

    public function edit(int $id): Response
    {
        return $this->view('services/form', ['title' => 'Edit service', 'service' => Service::find($id) ?? throw new HttpException(404)]);
    }

    public function update(int $id): Response
    {
        Service::find($id) ?? throw new HttpException(404);
        $v = $this->validated($id);
        if ($v->fails()) {
            return $this->backWithErrors("/services/$id/edit", $v->errors());
        }
        $d = $v->validated();
        Service::update($id, $d['name'], $d['description'], Money::toDecimal((int) Money::parse($d['price'])));
        AuditService::log('service.updated', 'service', $id, 'Updated service ' . $d['name'], ['price' => $d['price']]);

        return $this->redirectWith('/services', 'success', 'Service updated. Existing invoices keep their original prices.');
    }

    public function toggle(int $id): Response
    {
        $s = Service::find($id) ?? throw new HttpException(404);
        Service::setActive($id, !(bool) $s['is_active']);
        AuditService::log('service.toggled', 'service', $id, ((bool) $s['is_active'] ? 'Deactivated ' : 'Activated ') . $s['name']);

        return $this->redirectWith('/services', 'success', (bool) $s['is_active'] ? 'Service deactivated.' : 'Service activated.');
    }

    private function validated(?int $id = null): Validator
    {
        $v = Validator::make($this->request->body(), ['name' => 'required|max:120', 'description' => 'max:255', 'price' => 'required|money'], ['name' => 'Service name']);
        if (!$v->fails() && Service::nameTaken($v->validated()['name'], $id)) {
            $v->addError('name', 'A service with this name already exists.');
        }

        return $v;
    }
}
