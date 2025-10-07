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
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, $role)
    {
        if (!$request->user() || !$request->user()->roles()->where('name', $role)->exists()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Forbidden – Insufficient role',
            ], 403);
        }

        return $next($request);
    }
}
