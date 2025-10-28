<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class RoleController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        // return response()->json(Role::all());
        return response()->json(Role::with('permissions')->get());
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:roles,name',
            'display_name' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'status' => ['required', Rule::in([0, 1])],
            'permission_ids' => 'nullable|array',
            'permission_ids.*' => 'integer|exists:permissions,id'
        ]);

        $role = Role::create($request->only('name', 'display_name', 'description', 'status'));

        // Attach permissions if provided
        if ($request->has('permission_ids')) {
            $role->permissions()->sync($request->permission_ids);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Role created successfully',
            'data' => $role->load('permissions')
        ], 201);
    }

    /**
     * Display a specific role
     */
    public function show($id)
    {
        $role = Role::with('permissions')->findOrFail($id);
        return response()->json($role);
    }

    /**
     * Update a role
     */
    public function update(Request $request, $id)
    {
        $role = Role::findOrFail($id);

        $request->validate([
            'name' => 'required|string|unique:roles,name,' . $id,
            'display_name' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'status' => ['required', Rule::in([0, 1])],
            'permission_ids' => 'nullable|array',
            'permission_ids.*' => 'integer|exists:permissions,id'
        ]);

        $role->update($request->only('name', 'display_name', 'description', 'status'));

        // Update permissions
        if ($request->has('permission_ids')) {
            $role->permissions()->sync($request->permission_ids);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Role updated successfully',
            'data' => $role->load('permissions')
        ]);
    }

    /**
     * Delete a role
     */
    public function destroy($id)
    {
        $role = Role::findOrFail($id);
        $role->permissions()->detach();
        $role->delete();

        return response()->json(['status' => 'success', 'message' => 'Role deleted successfully']);
    }





    public function assignRole(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'role_id' => 'required|array',
            'role_id.*' => 'required|integer',
        ]);
        $user = User::findOrFail($request->user_id);
        $validRoles = Role::whereIn('id', $request->role_id)->pluck('id')->toArray();
        $invalidRoles = array_diff($request->role_id, $validRoles);
        if (empty($validRoles)) {
            return response()->json([
                'status' => 'error',
                'message' => 'None of the provided role IDs are valid.',
                'invalid_ids' => $invalidRoles
            ], 422);
        }
        $user->roles()->syncWithoutDetaching($validRoles);
        return response()->json([
            'status' => 'success',
            'message' => 'Roles assigned successfully.',
            'assigned_roles' => $validRoles,
            'invalid_roles_skipped' => $invalidRoles
        ]);
    }




    public function removeRole(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'role_id' => 'required|exists:roles,id',
        ]);

        $user = User::findOrFail($request->user_id);
        $user->roles()->detach($request->role_id);

        return response()->json([
            'status' => 'success',
            'message' => 'Role removed successfully',
        ]);
    }


    public function getUsersByRole($role_id)
    {
        try {
            // Check if role exists
            $role = Role::find($role_id);
            if (!$role) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Role not found'
                ], 404);
            }

            // Get all users assigned to this role
            $users = $role->users()->select('users.id', 'users.name', 'users.email')->get();

            return response()->json([
                'status' => 'success',
                'message' => 'Users fetched successfully',
                'data' => $users
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
