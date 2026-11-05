<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Response;
use App\Core\Validator;
use App\Models\Specialty;
use App\Services\AuditService;

final class SpecialtyController extends Controller
{
    public function index(): Response
    {
        return $this->view('specialties/index', ['title' => 'Specialties', 'specialties' => Specialty::all()]);
    }

    public function create(): Response
    {
        return $this->view('specialties/form', ['title' => 'New specialty', 'specialty' => null]);
    }

    public function store(): Response
    {
        $v = $this->validated();
        if ($v->fails()) {
            return $this->backWithErrors('/specialties/create', $v->errors());
        }
        $d = $v->validated();
        $id = Specialty::create($d['name'], $d['description']);
        AuditService::log('specialty.created', 'specialty', $id, 'Created specialty ' . $d['name']);

        return $this->redirectWith('/specialties', 'success', 'Specialty added.');
    }

    public function edit(int $id): Response
    {
        return $this->view('specialties/form', ['title' => 'Edit specialty', 'specialty' => Specialty::find($id) ?? throw new HttpException(404)]);
    }

    public function update(int $id): Response
    {
        Specialty::find($id) ?? throw new HttpException(404);
        $v = $this->validated($id);
        if ($v->fails()) {
            return $this->backWithErrors("/specialties/$id/edit", $v->errors());
        }
        $d = $v->validated();
        Specialty::update($id, $d['name'], $d['description']);
        AuditService::log('specialty.updated', 'specialty', $id, 'Updated specialty ' . $d['name']);

        return $this->redirectWith('/specialties', 'success', 'Specialty updated.');
    }

    public function toggle(int $id): Response
    {
        $s = Specialty::find($id) ?? throw new HttpException(404);
        Specialty::setActive($id, !(bool) $s['is_active']);
        AuditService::log('specialty.toggled', 'specialty', $id, ((bool) $s['is_active'] ? 'Deactivated ' : 'Activated ') . $s['name']);

        return $this->redirectWith('/specialties', 'success', (bool) $s['is_active'] ? 'Specialty deactivated.' : 'Specialty activated.');
    }

    private function validated(?int $id = null): Validator
    {
        $v = Validator::make($this->request->body(), ['name' => 'required|max:100', 'description' => 'max:255'], ['name' => 'Specialty name']);
        if (!$v->fails() && Specialty::nameTaken($v->validated()['name'], $id)) {
            $v->addError('name', 'A specialty with this name already exists.');
        }

        return $v;
    }
}
