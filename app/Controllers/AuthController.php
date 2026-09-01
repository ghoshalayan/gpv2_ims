<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\AuthService;
use App\Support\ActivityLog;
use App\Support\Auth;
use App\Support\Flash;

class AuthController
{
    public function __construct(private AuthService $auth = new AuthService()) {}

    public function showLogin(): string
    {
        if (Auth::check()) {
            return redirect('/dashboard');
        }
        return view('auth/login', [
            'title'    => 'Sign in',
            'username' => $_SESSION['_old_username'] ?? '',
        ]);
    }

    public function login(): string
    {
        $username = trim((string) ($_POST['username'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        if ($username === '' || $password === '') {
            Flash::error('Username and password are required.');
            $_SESSION['_old_username'] = $username;
            return redirect('/login');
        }

        $user = $this->auth->attempt($username, $password);
        if ($user === null) {
            ActivityLog::log('login', 'auth', null, [
                'label' => $username,
                'notes' => 'failed login attempt',
            ]);
            Flash::error('Invalid credentials, or account is inactive.');
            $_SESSION['_old_username'] = $username;
            return redirect('/login');
        }

        Auth::login($user);
        unset($_SESSION['_old_username']);
        ActivityLog::log('login', 'user', (int) $user['id'], [
            'label' => $user['name'],
            'user_id' => (int) $user['id'],
            'user_name' => $user['name'],
            'notes' => 'role=' . $user['role'],
        ]);
        Flash::success('Welcome back, ' . $user['name'] . '.');
        return redirect('/dashboard');
    }

    public function logout(): string
    {
        $u = Auth::user();
        if ($u !== null) {
            ActivityLog::log('logout', 'user', (int) $u['id'], [
                'label' => $u['name'] ?? null,
            ]);
        }
        Auth::logout();
        Flash::info('You have been logged out.');
        return redirect('/login');
    }
}
