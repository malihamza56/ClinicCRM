<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\BusinessRuleException;
use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Response;
use App\Core\Session;
use App\Models\User;
use App\Services\UserService;

final class UserController extends Controller
{
    public function index(): Response
    {
        return $this->view('users/index', ['title' => 'Staff accounts', 'users' => User::all()]);
    }

    public function create(): Response
    {
        return $this->view('users/form', ['title' => 'New staff account', 'user' => null, 'roles' => User::staffRoles()]);
    }

    public function store(): Response
    {
        $v = UserService::validate($this->request->body());
        if ($v->fails()) {
            return $this->backWithErrors('/users/create', $v->errors());
        }
        $created = UserService::create($v->validated());
        Session::flash('secret', ['label' => 'Account created for ' . $v->validated()['full_name'], 'value' => $created['temporary_password']]);

        return $this->redirectWith('/users', 'success', 'Staff account created.');
    }

    public function edit(int $id): Response
    {
        $user = User::findWithRole($id) ?? throw new HttpException(404);

        return $this->view('users/form', ['title' => 'Edit staff account', 'user' => $user, 'roles' => User::staffRoles()]);
    }

    public function update(int $id): Response
    {
        User::findWithRole($id) ?? throw new HttpException(404);
        $v = UserService::validate($this->request->body(), $id);
        if ($v->fails()) {
            return $this->backWithErrors("/users/$id/edit", $v->errors());
        }
        try {
            UserService::update($id, $v->validated());
        } catch (BusinessRuleException $e) {
            return $this->backWithErrors("/users/$id/edit", ['role_id' => $e->getMessage()]);
        }

        return $this->redirectWith('/users', 'success', 'Account updated.');
    }

    public function toggle(int $id): Response
    {
        $user = User::findWithRole($id) ?? throw new HttpException(404);
        try {
            UserService::setActive($id, !(bool) $user['is_active']);
        } catch (BusinessRuleException $e) {
            return $this->redirectWith('/users', 'error', $e->getMessage());
        }

        return $this->redirectWith('/users', 'success', ((bool) $user['is_active'] ? 'Account deactivated.' : 'Account activated.'));
    }

    public function resetPassword(int $id): Response
    {
        $user = User::findWithRole($id) ?? throw new HttpException(404);
        $temporary = UserService::resetPassword($id);
        Session::flash('secret', ['label' => 'New temporary password for ' . $user['full_name'], 'value' => (string) $temporary]);

        return $this->redirectWith('/users', 'success', 'Password reset. The user must choose a new one at next sign-in.');
    }
}
