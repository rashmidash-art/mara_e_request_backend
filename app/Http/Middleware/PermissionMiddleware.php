<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\Permission;
use App\Models\User;
use App\Models\Entiti;

class PermissionMiddleware
{
    /**
     * Handle an incoming request dynamically based on route + method.
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

        // Super Admin bypass (User only)
        if ($user instanceof User && $user->user_type === 0) {
            return $next($request);
        }

        // Resolve permission for this route
        $permission = $this->resolvePermission($request);

        // Log for debugging
        Log::info('Auth ID: ' . $user->id);
        Log::info('Permission checked: ' . $permission);

        // Entity login
        if ($user instanceof Entiti) {
            if (str_starts_with($permission, 'entities.')) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Forbidden – Entities cannot access this module',
                    'required_permission' => $permission,
                ], 403);
            }

            // Entities bypass for all other permissions
            return $next($request);
        }

        // Normal User → check assigned permissions
        if ($user instanceof User) {
            $userPermissions = $user->permissions()->pluck('name')->toArray();
            Log::info('User permissions: ', $userPermissions);

            if (!in_array($permission, $userPermissions)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Forbidden – You do not have permission to perform this action',
                    'required_permission' => $permission,
                ], 403);
            }

            return $next($request);
        }

        // fallback for any other unexpected type
        return response()->json([
            'status' => 'error',
            'message' => 'Unauthorized – Unknown user type',
        ], 401);
    }

    /**
     * Resolve the permission name based on route name or URI.
     */
    protected function resolvePermission(Request $request)
    {
        // 1️⃣ Use route name if available
        $routeName = $request->route()->getName();
        if ($routeName) {
            $parts = explode('.', $routeName);
            $module = $parts[0] ?? 'unknown';
            $action = $parts[1] ?? '';

            $actionMap = [
                'index'   => 'view',
                'show'    => 'view',
                'store'   => 'create',
                'update'  => 'update',
                'destroy' => 'delete',
            ];

            $action = $actionMap[$action] ?? $action;

            return "{$module}.{$action}";
        }

        // 2️⃣ Fallback for unnamed routes
        $routeUri = $request->route()->uri();
        $routeUri = str_replace('api/', '', $routeUri);
        $routeUri = preg_replace('/\{.*?\}/', '', $routeUri);
        $segments = array_filter(explode('/', trim($routeUri, '/')));
        $module = $segments[0] ?? 'unknown';
        $action = $segments[1] ?? null;

        $methodMap = [
            'GET'    => 'view',
            'POST'   => 'create',
            'PUT'    => 'update',
            'PATCH'  => 'update',
            'DELETE' => 'delete',
        ];

        $crudAction = $methodMap[$request->method()] ?? strtolower($request->method());

        $action = $action ?? $crudAction;
        $module = str_replace('_', '-', $module);

        return "{$module}.{$action}";
    }
}
