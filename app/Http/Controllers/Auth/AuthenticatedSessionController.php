<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    /**
     * Show the login page.
     */
    public function create(Request $request): Response
    {
        return Inertia::render('auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        // Update last login timestamp
        $user = Auth::user();
        $user->last_login_at = now();
        $user->save();

        $request->session()->regenerate();

        // Get the authenticated user
        $user = Auth::user();

        // Check if the user has the admin or super-admin role
        if ($user) {
            // Get user's roles directly from the database
            $userRoles = $user->roles()->pluck('name')->toArray();

            // Check if the user has admin or super-admin role
            if (in_array('admin', $userRoles) || in_array('super-admin', $userRoles)) {
                // Force redirect to admin dashboard, ignoring intended URL
                return redirect()->route('admin.dashboard');
            }
        }

        // For regular users, use the intended URL or fall back to dashboard
        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
