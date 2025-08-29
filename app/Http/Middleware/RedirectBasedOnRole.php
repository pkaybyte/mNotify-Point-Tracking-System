<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RedirectBasedOnRole
{
    public function handle(Request $request, Closure $next)
    {
        if (auth()->check()) {
            $user = auth()->user();
            
            // If user is on generic dashboard, redirect to role-specific dashboard
            if ($request->is('dashboard')) {
                if ($user->role === 'admin') {
                    return redirect('admin-dashboard');
                } elseif ($user->role === 'supervisor') {
                    return redirect('supervisor-dashboard');
                }
            }
        }
        
        return $next($request);
    }
}