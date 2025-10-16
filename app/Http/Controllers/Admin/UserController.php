<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Database\QueryException;

class UserController extends Controller
{
    /**
     * Display all users.
     */
    public function index()
    {
        try {
            $users = User::where('user_type' , 1 && 'status', 'Active')->get();
            return response()->json([
                'status' => 'success',
                'message' => 'Users retrieved successfully',
                'data' => $users
            ], 200);
        } catch (QueryException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve users',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a new user.
     */

    public function store(Request $request)
    {
        try {
            // Validation
            $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|email|unique:users,email',
                'password' => 'required|string|min:6',
                'employee_id' => 'required|string|unique:users,employee_id',
                'entiti_id' => 'required|integer|exists:entitis,id',
                'department_id' => 'required|integer|exists:departments,id',
                'loa' => 'required|numeric|min:0',
                'signature' => 'nullable|string',
                'status' => ['required', Rule::in(['Active', 'Inactive', 'Away'])],
                'roles' => 'sometimes|array',
            ]);

            // Check department belongs to entity
            $department = Department::find($request->department_id);
            if (!$department || $department->entiti_id != $request->entiti_id) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'The selected department does not belong to the specified entity.'
                ], 400);
            }

            // Check department budget
            $totalLoa = User::where('department_id', $department->id)->sum('loa');
            if (($totalLoa + $request->loa) > $department->budget) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Cannot create user: department budget exceeded.'
                ], 400);
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
                'signature' => $request->signature ?? '',
                'status' => $request->status,
            ]);

            // Sync roles if provided
            if ($request->has('roles')) {
                $user->roles()->sync($request->roles);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'User created successfully',
                'data' => $user
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (QueryException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create user',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    // public function store(Request $request)
    // {
    //     $request->validate([
    //         'name' => 'required|string|max:255',
    //         'email' => 'required|email|unique:users,email',
    //         'password' => 'required|string|min:6',
    //         'employee_id' => 'required|string|unique:users,employee_id',
    //         'entiti_id' => 'required|integer|exists:entitis,id',
    //         'department_id' => [
    //             'required',
    //             'integer',
    //             'exists:departments,id',
    //             function ($attribute, $value, $fail) use ($request) {
    //                 $department = Department::find($value);
    //                 if ($department && $department->entiti_id != $request->entiti_id) {
    //                     $fail('The selected department does not belong to the specified entity.');
    //                 }
    //             },
    //         ],
    //         'loa' => 'required|numeric|min:0',
    //         'signature' => 'nullable|string',
    //         'status' => ['required', Rule::in(['Active', 'Inactive', 'Away'])],
    //     ]);


    //     try {
    //         $user = User::create([
    //             'name' => $request->name,
    //             'email' => $request->email,
    //             'password' => Hash::make($request->password),
    //             'employee_id' => $request->employee_id,
    //             'entiti_id' => $request->entiti_id,
    //             'department_id' => $request->department_id,
    //             'loa' => $request->loa,
    //             'signature' => $request->signature,
    //             'status' => $request->status,
    //         ]);

    //         return response()->json([
    //             'status' => 'success',
    //             'message' => 'User created successfully',
    //             'data' => $user
    //         ], 200);
    //     } catch (QueryException $e) {
    //         return response()->json([
    //             'status' => 'error',
    //             'message' => 'Failed to create user',
    //             'error' => $e->getMessage()
    //         ], 500);
    //     }
    // }

    /**
     * Display a specific user.
     */
    public function show($id)
    {
        try {
            $user = User::findOrFail($id);

            return response()->json([
                'status' => 'success',
                'message' => 'User retrieved successfully',
                'data' => $user
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'User not found'
            ], 404);
        } catch (QueryException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve user',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update a user.
     */
    public function update(Request $request, $id)
    {
        try {
            // Validation
            $request->validate([
                'name' => 'sometimes|required|string|max:255',
                'email' => ['sometimes', 'required', 'email', Rule::unique('users')->ignore($id)],
                'password' => 'sometimes|required|string|min:6',
                'employee_id' => ['sometimes', 'required', Rule::unique('users')->ignore($id)],
                'entiti_id' => 'sometimes|required|integer|exists:entitis,id',
                'department_id' => 'sometimes|required|integer|exists:departments,id',
                'loa' => 'sometimes|required|numeric|min:0',
                'signature' => 'nullable|string',
                'status' => ['sometimes', 'required', Rule::in(['Active', 'Inactive', 'Away'])],
                'roles' => 'sometimes|array',
            ]);

            $user = User::findOrFail($id);

            // Determine department for budget check
            $department_id = $request->department_id ?? $user->department_id;
            $department = Department::find($department_id);

            // Department-entity check
            $entiti_id = $request->entiti_id ?? $user->entiti_id;
            if (!$department || $department->entiti_id != $entiti_id) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'The selected department does not belong to the specified entity.'
                ], 400);
            }

            // Budget check if LOA is being updated
            if ($request->has('loa')) {
                $totalLoa = User::where('department_id', $department->id)
                    ->where('id', '!=', $user->id)
                    ->sum('loa');

                if (($totalLoa + $request->loa) > $department->budget) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Cannot update LOA: department budget exceeded.'
                    ], 400);
                }
            }

            // Fill fields
            $data = $request->only([
                'name',
                'email',
                'employee_id',
                'entiti_id',
                'department_id',
                'loa',
                'status'
            ]);

            $data['signature'] = $request->signature ?? '';
            $user->fill($data);

            if ($request->has('password')) {
                $user->password = Hash::make($request->password);
            }

            $user->save();

            if ($request->has('roles')) {
                $user->roles()->sync($request->roles);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'User updated successfully',
                'data' => $user
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'User not found'
            ], 404);
        } catch (QueryException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update user',
                'error' => $e->getMessage()
            ], 500);
        }
    }




    /**
     * Delete a user.
     */
    public function destroy($id)
    {
        try {
            $user = User::findOrFail($id);
            $user->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'User deleted successfully'
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'User not found'
            ], 404);
        } catch (QueryException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete user',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
