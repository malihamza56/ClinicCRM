<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\BusinessRuleException;
use App\Core\Controller;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;
use App\Models\Doctor;
use App\Models\Specialty;
use App\Models\User;
use App\Services\DoctorService;

final class DoctorController extends Controller
{
    public function index(): Response
    {
        $status = $this->request->string('status');
        $filters = ['q' => $this->request->string('q'), 'specialty_id' => $this->request->int('specialty_id'), 'status' => in_array($status, ['active', 'inactive'], true) ? $status : ''];

        return $this->view('doctors/index', ['title' => 'Doctors', 'doctors' => Doctor::search($filters), 'filters' => $filters, 'specialties' => Specialty::all()]);
    }

    public function show(int $id): Response
    {
        $doctor = Doctor::find($id) ?? throw new HttpException(404);

        return $this->view('doctors/show', ['title' => $doctor['full_name'], 'doctor' => $doctor, 'schedules' => Doctor::schedules($id)]);
    }

    public function create(): Response
    {
        return $this->view('doctors/form', ['title' => 'New doctor', 'doctor' => null, 'specialties' => Specialty::all(true)]);
    }

    public function store(): Response
    {
        $body = $this->request->body();
        $account = Validator::make($body, ['full_name' => 'required|name|max:120', 'email' => 'required|email|max:190', 'phone' => 'phone'], ['full_name' => 'Full name']);
        $profile = DoctorService::validateProfile($body);
        $errors = $account->errors() + $profile->errors();
        if (!isset($errors['email']) && User::emailTaken(strtolower((string) $account->validated()['email']))) {
            $errors['email'] = 'Another account already uses this email address.';
        }
        if ($errors !== []) {
            return $this->backWithErrors('/doctors/create', $errors);
        }

        $created = DoctorService::createWithAccount($account->validated(), $profile->validated());
        Session::flash('secret', ['label' => 'Doctor account created for ' . $account->validated()['full_name'], 'value' => $created['temporary_password']]);

        return $this->redirectWith('/doctors/' . $created['doctor_id'], 'success', 'Doctor added. Now set their weekly schedule below.');
    }

    public function edit(int $id): Response
    {
        $doctor = Doctor::find($id) ?? throw new HttpException(404);

        return $this->view('doctors/form', ['title' => 'Edit doctor', 'doctor' => $doctor, 'specialties' => Specialty::all(true)]);
    }

    public function update(int $id): Response
    {
        Doctor::find($id) ?? throw new HttpException(404);
        $v = DoctorService::validateProfile($this->request->body());
        if ($v->fails()) {
            return $this->backWithErrors("/doctors/$id/edit", $v->errors());
        }
        DoctorService::updateProfile($id, $v->validated());

        return $this->redirectWith("/doctors/$id", 'success', 'Doctor profile updated.');
    }

    public function toggle(int $id): Response
    {
        $doctor = Doctor::find($id) ?? throw new HttpException(404);
        try {
            DoctorService::setActive($id, !(bool) $doctor['is_active']);
        } catch (BusinessRuleException $e) {
            return $this->redirectWith("/doctors/$id", 'error', $e->getMessage());
        }

        return $this->redirectWith("/doctors/$id", 'success', (bool) $doctor['is_active'] ? 'Doctor deactivated.' : 'Doctor activated.');
    }

    public function addSchedule(int $id): Response
    {
        Doctor::find($id) ?? throw new HttpException(404);
        $v = Validator::make($this->request->body(), ['weekday' => 'required|int', 'start_time' => 'required|time', 'end_time' => 'required|time'], ['start_time' => 'Start time', 'end_time' => 'End time']);
        if ($v->fails()) {
            return $this->redirectWith("/doctors/$id", 'error', implode(' ', $v->errors()));
        }
        $d = $v->validated();
        try {
            DoctorService::addSchedule($id, (int) $d['weekday'], $d['start_time'], $d['end_time']);
        } catch (BusinessRuleException $e) {
            return $this->redirectWith("/doctors/$id", 'error', $e->getMessage());
        }

        return $this->redirectWith("/doctors/$id", 'success', 'Working hours added.');
    }

    public function removeSchedule(int $id, int $sid): Response
    {
        Doctor::find($id) ?? throw new HttpException(404);
        DoctorService::removeSchedule($id, $sid);

        return $this->redirectWith("/doctors/$id", 'success', 'Working hours removed. Existing appointments are not affected.');
    }
}
