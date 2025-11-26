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
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 401);
        }

        // ===== LOG: User + Route =====
        Log::info('🔐 Permission Check', [
            'user_id'   => $user->id,
            'user_type' => $user instanceof User ? $user->user_type : 'entity',
            'route'     => $request->route()->getName(),
            'method'    => $request->method()
        ]);

        // ======================================
        // ✅ 1. SUPER ADMIN → FULL ACCESS
        // ======================================
        if ($user instanceof User && $user->user_type === 0) {
            Log::info("🟢 SUPER ADMIN bypass");
            return $next($request);
        }

        // ======================================
        // ✅ 2. ENTITY USER (entiti-api)
        // ======================================
        if ($user instanceof Entiti) {

            $permission = $this->resolvePermission($request);

            Log::info("🟦 ENTITY Permission Check: $permission");

            // allow only its own entity view
            if ($request->route()->getName() === 'entity.itself') {
                Log::info("🟢 ENTITY allowed own details");
                return $next($request);
            }

            // allow only "entities.view"
            if ($permission === 'entities.view') {
                Log::info("🟢 ENTITY allowed entity list");
                return $next($request);
            }

            // block other entity actions
            if (str_starts_with($permission, 'entities.')) {
                Log::warning("🔴 ENTITY blocked: $permission");
                return response()->json([
                    'status' => 'error',
                    'message' => 'Forbidden – You cannot access other entity details',
                ], 403);
            }

            // allow all other modules
            Log::info("🟢 ENTITY allowed general access");
            return $next($request);
        }

        // ======================================
        // ✅ 3. NORMAL USER (Admin)
        // ======================================
        if ($user instanceof User) {

            $permission = $this->resolvePermission($request);
            Log::info("📌 Required Permission: $permission");

            // Fetch user's assigned permissions
            $userPermissions = $user
                ->roles()
                ->with('permissions')
                ->get()
                ->pluck('permissions')
                ->flatten()
                ->pluck('name')
                ->toArray();

            Log::info("🧾 User Permissions:", $userPermissions);

            // Check if user has required permission
            if (!in_array($permission, $userPermissions)) {
                Log::warning("❌ Permission Denied: $permission");

                return response()->json([
                    'status' => 'error',
                    'message' => 'Forbidden – You do not have permission to perform this action',
                    'required_permission' => $permission,
                ], 403);
            }

            Log::info("🟢 Permission Granted: $permission");
            return $next($request);
        }

        return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 401);
    }

    /**
     * Resolve the permission name based on route name or URI.
     */
    protected function resolvePermission(Request $request)
    {
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

        // fallback
        $routeUri = $request->route()->uri();
        $routeUri = str_replace('api/', '', $routeUri);
        $routeUri = preg_replace('/\{.*?\}/', '', $routeUri);

        $segments = array_filter(explode('/', trim($routeUri, '/')));
        $module = $segments[0] ?? 'unknown';

        $methodMap = [
            'GET'    => 'view',
            'POST'   => 'create',
            'PUT'    => 'update',
            'PATCH'  => 'update',
            'DELETE' => 'delete',
        ];

        $action = $methodMap[$request->method()] ?? 'unknown';
        $module = str_replace('_', '-', $module);

        return "{$module}.{$action}";
    }
}
