<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, ...$permissions): Response
    {
       $user = $request->user();

        if (!$user) {
            abort(401, 'Unauthenticated.');
        }

        $role = $user->role;

        if (!$role) {
            abort(403, 'No role assigned to this user.');
        }

        // Permit access if the role has ANY one of the requested permissions.
        $allowed = $role->permissions()
            ->whereIn('permissions.name', $permissions)
            ->exists();

        abort_unless(
            $allowed,
            403,
            'You do not have permission to perform this action.'
        );

        return $next($request);
    }
}
