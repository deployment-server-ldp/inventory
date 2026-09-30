<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route guard: `perm:a.b` or `perm:a.b|c.d` (user needs ANY of the listed permissions).
 * This is the server-side enforcement of RBAC; menus are hidden only for convenience.
 */
class RequirePermission
{
    public function handle(Request $request, Closure $next, string $permissions): Response
    {
        $user = $request->user();
        if (! $user || ! $user->hasAnyPermission(explode('|', $permissions))) {
            abort(403, 'You do not have permission to access this page.');
        }

        return $next($request);
    }
}
