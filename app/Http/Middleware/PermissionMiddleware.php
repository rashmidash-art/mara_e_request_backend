<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PermissionMiddleware
{
    /**
     * Handle an incoming request dynamically based on the current route.
     */
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized – User not authenticated',
            ], 401);
        }

        // Normalize the route URI to match permission name in DB
        $routeUri = $request->route()->uri(); // e.g., "api/entities/{id}"
        $routeUri = str_replace('api/', '', $routeUri);
        $routeUri = preg_replace('/\{.*?\}/', '', $routeUri);
        $baseUri = explode('/', trim($routeUri, '/'))[0]; // => "entities"

        if (!$user->hasPermission($baseUri)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Forbidden – You do not have permission to access this route',
                'required_permission' => $baseUri,
            ], 403);
        }

        return $next($request);
    }
}
