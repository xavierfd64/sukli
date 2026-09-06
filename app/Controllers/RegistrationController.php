<?php

declare(strict_types=1);

namespace Sukli\Controllers;

use Sukli\Core\Auth;
use Sukli\Core\Controller;
use Sukli\Core\Database;
use Sukli\Core\Request;
use Sukli\Core\Session;
use Sukli\Services\AuditService;
use Sukli\Services\RegistrationService;

class RegistrationController extends Controller
{
    public function show(Request $request): void
    {
        $this->view('auth/register', ['pageTitle' => 'Create Your Account', 'error' => Session::flash('error')], 'layouts/blank');
    }

    public function store(Request $request): void
    {
        $ownerName = $request->trimmed('owner_name');
        $businessName = $request->trimmed('business_name');
        $username = $request->trimmed('username');
        $email = $request->trimmed('email');
        $password = (string) $request->input('password', '');

        if ($ownerName === '' || $businessName === '' || $username === '' || $email === '' || strlen($password) < 8) {
            Session::flash('error', 'Please fill in all fields. Password must be at least 8 characters.');
            $this->redirect('/register');
        }

        try {
            $result = RegistrationService::register($ownerName, $businessName, $username, $email, $password);
        } catch (\RuntimeException $e) {
            Session::flash('error', $e->getMessage());
            $this->redirect('/register');
        }

        $user = Database::one(
            "SELECT u.*, r.role_key FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = ?",
            [$result['user_id']]
        );
        Auth::establishSession($user);

        AuditService::log('register', 'auth', 'organization', $result['organization_id'], null, ['business_name' => $businessName]);
        Session::flash('success', 'Welcome to Sukli! Your 14-day free trial has started.');
        $this->redirect('/dashboard');
    }
}
