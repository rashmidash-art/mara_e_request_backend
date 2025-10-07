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
        return response()->json(Role::all());
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
            'employee_id' => 'required|string|unique:users,employee_id',
            'entiti_id' => 'required|integer|exists:entities,id',
            'department_id' => [
                'required',
                'integer',
                'exists:departments,id',
                function ($attribute, $value, $fail) use ($request) {
                    $department = Department::find($value);
                    if ($department && $department->entiti_id != $request->entiti_id) {
                        $fail('The selected department does not belong to the specified entity.');
                    }
                },
            ],
            'loa' => 'required|numeric|min:0',
            'roles' => 'nullable|array',
            'roles.*' => 'integer|exists:roles,id',
            'signature' => 'nullable|string',
            'status' => ['required', Rule::in(['Active', 'Inactive', 'Away'])],
        ]);

        try {
            // Check department budget
            $department = Department::find($request->department_id);
            $currentUsersLOA = User::where('department_id', $request->department_id)->sum('loa');
            $newTotalLOA = $currentUsersLOA + $request->loa;

            if ($newTotalLOA > $department->budget) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Cannot create user. Department budget exceeded.',
                    'department_budget' => $department->budget,
                    'current_total_loa' => $currentUsersLOA,
                    'requested_loa' => $request->loa
                ], 401);
            }

            // Create user
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'employee_id' => $request->employee_id,
                'entiti_id' => $request->entiti_id,
                'department_id' => $request->department_id,
                'loa' => $request->loa,
                'signature' => $request->signature,
                'status' => $request->status,
            ]);

            // Assign roles if provided
            if ($request->has('roles') && !empty($request->roles)) {
                $validRoles = Role::whereIn('id', $request->roles)->pluck('id')->toArray();
                $user->roles()->sync($validRoles);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'User created successfully',
                'data' => $user
            ], 201);
        } catch (QueryException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create user',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $role = Role::findOrFail($id);
        return response()->json($role);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $role = Role::findOrFail($id);

        $request->validate([
            'name' => 'required|unique:roles,name,' . $id,
            'display_name' => 'nullable|string',
            'description' => 'nullable|string',
            'status' => 'in:0,1',
        ]);

        $role->update($request->only(['name', 'display_name', 'description', 'status']));

        return response()->json([
            'status' => 'success',
            'message' => 'Role updated successfully',
            'data' => $role
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $role = Role::findOrFail($id);
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
}
