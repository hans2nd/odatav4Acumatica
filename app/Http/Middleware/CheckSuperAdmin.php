<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;

class CheckSuperAdmin
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!Auth::check()) {
            return redirect()->route('login');
        }

        $user = Auth::user();

        if (array_key_exists('is_active', $user->getAttributes()) && !$user->is_active) {
            abort(403, 'Your account has been deactivated.');
        }

        // Strict enforcement: User must genuinely be a Super Admin
        // This bypasses granular permissions (like settings.manage) which could have been mistakenly granted.
        if (!method_exists($user, 'isSuperAdmin') || !$user->isSuperAdmin()) {
            abort(403, 'Unauthorized access. Only authentic Super Administrators may perform this action.');
        }

        return $next($request);
    }
}
