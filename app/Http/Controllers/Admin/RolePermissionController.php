<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class RolePermissionController extends Controller
{
    /**
     * Assign multiple permissions to a role.
     */
    public function allpermissions()
    {
        $permissions = Permission::all();

        if ($permissions->isEmpty()) {
            return response()->json([
                'status' => 'error',
                'message' => 'No permissions found.',
            ], 404);
        }

        $grouped = $permissions->groupBy(function ($permission) {
            [$module, $action] = explode('.', $permission->name);

            // normalize key
            $normalizedModule = Str::plural($module);

            return "{$normalizedModule}.{$action}";
        })->map(function ($group) {
            // return the first one (canonical)
            return $group->first();
        })->values();

        return response()->json([
            'status' => 'success',
            'message' => 'All permissions fetched successfully.',
            'data' => $grouped,
        ], 200);
    }

    public function manageRolePermissions(Request $request)
    {
        $request->validate([
            'role_id' => 'required|exists:roles,id',
            'permission_ids' => 'required|array',
            'permission_ids.*' => 'integer|exists:permissions,id',
        ]);

        $role = Role::findOrFail($request->role_id);

        $permissions = Permission::whereIn('id', $request->permission_ids)->get();

        $finalPermissionIds = [];

        foreach ($permissions as $permission) {
            [$module, $action] = explode('.', $permission->name);

            $variants = [
                Str::singular($module).".{$action}",
                Str::plural($module).".{$action}",
            ];

            $matching = Permission::whereIn('name', $variants)->pluck('id')->toArray();

            $finalPermissionIds = array_merge($finalPermissionIds, $matching);
        }

        $role->permissions()->sync(array_unique($finalPermissionIds));

        return response()->json([
            'status' => 'success',
            'message' => 'Permissions updated successfully.',
            'role_id' => $role->id,
            'assigned_permissions' => array_values(array_unique($finalPermissionIds)),
        ]);
    }

    public function getRolePermissions($role_id)
    {
        $role = Role::with('permissions')->findOrFail($role_id);

        $permissions = $role->permissions
            ->groupBy(function ($permission) {
                [$module, $action] = explode('.', $permission->name);

                return Str::plural($module).".{$action}";
            })
            ->map(fn ($group) => $group->first())
            ->values();

        return response()->json([
            'status' => 'success',
            'role' => $role->display_name ?? $role->name,
            'permissions' => $permissions,
        ]);
    }

    // public function assignPermissions(Request $request)
    // {
    //     $request->validate([
    //         'role_id' => 'required|exists:roles,id',
    //         'permission_ids' => 'required|array',
    //         'permission_ids.*' => 'integer|exists:permissions,id',
    //     ]);

    //     $role = Role::findOrFail($request->role_id);

    //     // Sync without removing old permissions
    //     $role->permissions()->syncWithoutDetaching($request->permission_ids);

    //     return response()->json([
    //         'status' => 'success',
    //         'message' => 'Permissions assigned successfully.',
    //         'assigned_permissions' => $request->permission_ids,
    //     ]);
    // }

    /**
     * Remove a specific permission from a role.
     */
    // public function removePermission(Request $request)
    // {
    //     $request->validate([
    //         'role_id' => 'required|exists:roles,id',
    //         'permission_id' => 'required|exists:permissions,id',
    //     ]);

    //     $role = Role::findOrFail($request->role_id);
    //     $role->permissions()->detach($request->permission_id);

    //     return response()->json([
    //         'status' => 'success',
    //         'message' => 'Permission removed successfully.',
    //     ]);
    // }

    /**
     * Get all permissions for a specific role.
     */
}
