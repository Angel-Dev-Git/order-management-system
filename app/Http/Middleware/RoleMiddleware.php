<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    // public function handle(Request $request, Closure $next): Response
    // {
    //     return $next($request);
    // }

     public function handle(Request $request, Closure $next, $role = null) 
    {
    //     if ($request->user()?->role?->name !== $role) {               //?-> is null safe operator check if user or role is null
    //         // dd($request->user()?->role?->name, $role);
    //         return response()->json([
    //             'message' => 'Unauthorized access',
    //         ], 403);
    //     }

    //     return $next($request);
        if ($role === null) {
                return response()->json([
                    'message' => 'Role parameter is missing.',
                ], 500);
            }

            if ($request->user()?->role?->name !== $role) {
                return response()->json([
                    'message' => 'Unauthorized access.',
                ], 403);
            }

            return $next($request);
    }
}
