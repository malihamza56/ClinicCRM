<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;
use App\Services\AuthService;

final class AuthController extends Controller
{
    public function home(): Response
    {
        return $this->redirect(Auth::check() ? '/dashboard' : '/login');
    }

    public function showLogin(): Response
    {
        if (Auth::check()) {
            return $this->redirect('/dashboard');
        }

        return $this->view('auth/login', ['title' => 'Sign in'], 'layouts/auth');
    }

    public function login(): Response
    {
        $email = $this->request->string('email');
        $password = (string) $this->request->input('password', '');

        $result = $email === '' || $password === ''
            ? ['ok' => false, 'message' => 'Please enter your email and password.']
            : AuthService::attempt($email, $password, $this->request->ip());

        if (!$result['ok']) {
            Session::flash('old', ['email' => $email]);

            return $this->redirectWith('/login', 'error', (string) $result['message']);
        }

        $user = Auth::user();
        if ($user !== null && (bool) $user['must_change_password']) {
            return $this->redirectWith('/account/password', 'warning', 'Please choose a new password before you continue.');
        }

        return $this->redirect($this->safeIntendedPath());
    }

    public function logout(): Response
    {
        AuthService::logout();

        return $this->redirectWith('/login', 'success', 'You have been signed out.');
    }

    public function showPassword(): Response
    {
        return $this->view('auth/password', [
            'title'  => 'Change password',
            'forced' => (bool) (Auth::user()['must_change_password'] ?? false),
        ]);
    }

    public function changePassword(): Response
    {
        $v = Validator::make($this->request->body(), [
            'current_password' => 'required',
            'new_password'     => 'required',
            'confirm_password' => 'required|same:new_password',
        ], ['current_password' => 'Current password', 'new_password' => 'New password', 'confirm_password' => 'Confirmation']);

        if ($v->fails()) {
            return $this->backWithErrors('/account/password', $v->errors());
        }

        $d = $v->validated();
        $result = AuthService::changePassword((int) Auth::id(), $d['current_password'], $d['new_password']);
        if (!$result['ok']) {
            return $this->backWithErrors('/account/password', ['new_password' => (string) $result['message']]);
        }

        // New credentials: start a fresh session ID so any old one stops working.
        Auth::login(Auth::user() ?? []);

        return $this->redirectWith('/dashboard', 'success', 'Your password was changed.');
    }

    /** Only follow in-app paths: an attacker must not be able to bounce users to another website after login. */
    private function safeIntendedPath(): string
    {
        $path = Session::get('intended_path');
        Session::forget('intended_path');

        return is_string($path) && preg_match('#^/(?!/)[A-Za-z0-9_\-/]*$#', $path) === 1 && $path !== '/login' ? $path : '/dashboard';
    }
}
