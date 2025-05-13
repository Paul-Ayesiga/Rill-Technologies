<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpFoundation\Response;

class RedirectIfAdmin
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()) {
            // Get user's roles directly from the database
            $userRoles = $request->user()->roles()->pluck('name')->toArray();

            // Check if user has admin or super-admin role
            if (in_array('admin', $userRoles) || in_array('super-admin', $userRoles)) {
                return redirect()->route('admin.dashboard');
            }
        }

        return $next($request);
    }
}
