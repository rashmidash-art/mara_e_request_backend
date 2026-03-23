<?php

namespace App\Http\Middleware;

use App\Models\Entiti;
use App\Models\Permission;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PermissionMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 401);
        }

        // ===== LOG: User + Route =====
        Log::info(' Permission Check', [
            'user_id' => $user->id,
            'user_type' => $user instanceof User ? $user->user_type : 'entity',
            'route' => $request->route()->getName(),
            'method' => $request->method(),
        ]);

        // ======================================
        //  1. SUPER ADMIN → FULL ACCESS
        // ======================================
        if ($user instanceof User && $user->user_type === 0) {
            Log::info(' SUPER ADMIN bypass');

            return $next($request);
        }

        // ======================================
        //  2. ENTITY USER (entiti-api)
        // ======================================
        // if ($user instanceof Entiti) {

        //     $permission = $this->resolvePermission($request);

        //     Log::info("🟦 ENTITY Permission Check: $permission");

        //     if ($request->route()->getName() === 'entity.itself') {
        //         Log::info(' ENTITY allowed own details');

        //         return $next($request);
        //     }

        //     if ($permission === 'entities.view') {
        //         Log::info(' ENTITY allowed entity list');

        //         return $next($request);
        //     }

        //     if (str_starts_with($permission, 'entities.')) {
        //         Log::warning(" ENTITY blocked: $permission");

        //         return response()->json([
        //             'status' => 'error',
        //             'message' => 'Forbidden – You cannot access other entity details',
        //         ], 403);
        //     }

        //     Log::info(' ENTITY allowed general access');

        //     return $next($request);
        // }

        if ($user instanceof Entiti) {

            $permission = $this->resolvePermission($request);

            Log::info(" ENTITY Permission Check: $permission");

            //  Allow viewing own entity
            if ($request->route()->getName() === 'entity.itself') {
                return $next($request);
            }

            //  Allow listing entities
            if ($permission === 'entities.view') {
                return $next($request);
            }

            //   ALLOW updating own entity
            if ($permission === 'entities.update') {

                $routeId = $request->route('entity') ?? $request->route('id');

                if ((int) $routeId === (int) $user->id) {
                    Log::info(' ENTITY allowed to update own profile');

                    return $next($request);
                }

                Log::warning(' ENTITY tried to update another entity');
                Log::info('ENTITY DEBUG', [
                    'route_param_entity' => $request->route('entity'),
                    'route_param_id' => $request->route('id'),
                    'final_route_id' => $routeId,
                    'auth_user_id' => $user->id,
                ]);
                Log::info('UPDATE DEBUG', [
                    'auth_user_id' => $user->id,
                    'route_entity' => $request->route('entity'),
                    'route_id' => $request->route('id'),
                ]);

                return response()->json([
                    'status' => 'error',
                    'message' => 'Forbidden – You cannot update other entities',
                ], 403);
            }

            //  Block all other entity actions
            if (str_starts_with($permission, 'entities.')) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Forbidden – You cannot access other entity details',
                ], 403);
            }

            return $next($request);
        }

        // ======================================
        //  3. NORMAL USER (Admin)
        // ======================================
        if ($user instanceof User) {

            $permission = $this->resolvePermission($request);
            Log::info(" Required Permission: $permission");

            // Fetch user's assigned permissions
            $userPermissions = $user
                ->roles()
                ->with('permissions')
                ->get()
                ->pluck('permissions')
                ->flatten()
                ->pluck('name')
                ->toArray();

            Log::info(' User Permissions:', $userPermissions);

            // Check if user has required permission
            if (! $this->permissionExists($permission, $userPermissions)) {
                Log::warning(" Permission Denied: $permission");

                return response()->json([
                    'status' => 'error',
                    'message' => 'Forbidden – You do not have permission to perform this action',
                    'required_permission' => $permission,
                ], 403);
            }

            Log::info(" Permission Granted: $permission");

            return $next($request);
        }

        return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 401);
    }

    /**
     * Resolve the permission name based on route name or URI.
     */
    protected function permissionExists(string $required, array $available): bool
    {
        $variants = $this->permissionVariants($required);

        foreach ($variants as $variant) {
            if (in_array($variant, $available)) {
                return true;
            }
        }

        return false;
    }

    protected function permissionVariants(string $permission): array
    {
        [$module, $action] = explode('.', $permission);

        $singular = Str::singular($module);
        $plural = Str::plural($module);

        return array_unique([
            "{$module}.{$action}",
            "{$singular}.{$action}",
            "{$plural}.{$action}",
        ]);
    }

    protected function resolvePermission(Request $request)
    {
        //  If route has a name (recommended)
        $routeName = $request->route()->getName();

        if ($routeName) {
            $parts = explode('.', $routeName);

            // apiResource generates names like "entities.index"
            $module = $parts[0] ?? 'unknown';
            $action = $parts[1] ?? '';

            // Convert index/show → view, store → create, etc.
            $actionMap = [
                'index' => 'view',
                'show' => 'view',
                'store' => 'create',
                'update' => 'update',
                'destroy' => 'delete',
            ];

            $action = $actionMap[$action] ?? $action;

            return "{$module}.{$action}";
        }

        //  Fallback using URI
        $uri = str_replace('api/', '', $request->route()->uri());
        $segments = explode('/', trim($uri, '/'));

        $module = $segments[0] ?? 'unknown';

        // force plural (entity → entities)
        if (! str_ends_with($module, 's')) {
            $module .= 's';
        }

        $methodMap = [
            'GET' => 'view',
            'POST' => 'create',
            'PUT' => 'update',
            'PATCH' => 'update',
            'DELETE' => 'delete',
        ];

        $action = $methodMap[$request->method()] ?? 'unknown';

        return "{$module}.{$action}";
    }
}
