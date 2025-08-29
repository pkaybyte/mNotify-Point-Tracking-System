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
     * Redirects users based on their role after successful login.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        try {
            $request->authenticate();
            $request->session()->regenerate();

            // Get the authenticated user
            $user = $request->user();
            
            // Log successful login for debugging
            \Log::info('User logged in successfully', ['user_id' => $user->id, 'email' => $user->email, 'role' => $user->role]);

            // Direct redirect based on user role
            switch ($user->role) {
                case 'admin':
                    return redirect()->intended(route('admin.dashboard'));
                case 'supervisor':
                    return redirect()->intended(route('supervisor.dashboard'));
                default:
                    return redirect()->intended(route('dashboard'));
            }
        } catch (\Exception $e) {
            \Log::error('Login failed', ['error' => $e->getMessage()]);
            return back()->withErrors([
                'email' => 'The provided credentials do not match our records.',
            ]);
        }
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        // Get user info before logout for logging
        $user = $request->user();
        
        // Perform logout
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        
        // Log successful logout (only if we had a user)
        if ($user) {
            \Log::info('User logged out successfully', [
                'user_id' => $user->id,
                'email' => $user->email
            ]);
        }
        
        // Simple redirect to home without any additional data
        return redirect('/');
    }
}