<?php

declare(strict_types=1);

namespace Sukli\Controllers;

use Sukli\Core\Auth;
use Sukli\Core\Controller;
use Sukli\Core\Request;
use Sukli\Core\Session;
use Sukli\Services\AuditService;

class AuthController extends Controller
{
    public function showLogin(Request $request): void
    {
        $this->view('auth/login', ['pageTitle' => 'Login', 'error' => Session::flash('error')], 'layouts/blank');
    }

    public function login(Request $request): void
    {
        $username = $request->trimmed('username');
        $password = (string) $request->input('password', '');

        if ($username === '' || $password === '') {
            Session::flash('error', 'Please enter your username and password.');
            $this->redirect('/login');
        }

        $result = Auth::attempt($username, $password, $request->ip(), $request->userAgent());

        if (!$result['ok']) {
            AuditService::log('login_failed', 'auth', 'user', null, null, ['username' => $username]);
            Session::flash('error', $result['message']);
            $this->redirect('/login');
        }

        AuditService::log('login', 'auth', 'user', $result['user']['id']);
        $this->redirect('/dashboard');
    }

    public function logout(Request $request): void
    {
        AuditService::log('logout', 'auth', 'user', Auth::id());
        Auth::logout();
        $this->redirect('/login');
    }
}
