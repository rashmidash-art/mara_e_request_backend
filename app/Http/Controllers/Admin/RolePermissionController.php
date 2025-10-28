<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Role;
use App\Models\Permission;

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
                'message' => 'No permissions found.'
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'All permissions fetched successfully.',
            'data' => $permissions
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

        // Sync permissions: will assign new ones and remove unchecked ones
        $role->permissions()->sync($request->permission_ids);

        return response()->json([
            'status' => 'success',
            'message' => 'Permissions updated successfully.',
            'role_id' => $role->id,
            'assigned_permissions' => $request->permission_ids,
        ]);
    }




    public function getRolePermissions($role_id)
    {
        $role = Role::with('permissions')->findOrFail($role_id);

        return response()->json([
            'status' => 'success',
            'role' => $role->display_name ?? $role->name,
            'permissions' => $role->permissions,
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
