<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Entiti;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Storage;

class UserController extends Controller
{
    /**
     * Display all users.
     */
    public function index(Request $request)
    {
        try {

            $users = $request->user();
            if ($users instanceof User && $users->user_type == 0) {
                // $departments = Department::all();
                $users = User::with('roles')->where('user_type', 1)->get();
            } else if ($users instanceof Entiti) {
                $users = User::with('roles')->where('entiti_id', $users->id)->where('user_type', 1)->get();
                // $departments = Department::where('entiti_id', $users->id)->get();
            } else if ($users instanceof User) {
                $users = User::with('roles')->where('user_type', 1)->get();
                // $departments = Department::all();
                // OR restrict by permissions if needed
                // $departments = Department::whereIn('id', $user->departments()->pluck('department_id'))->get();
            }
            // $users = User::with('roles')->where('user_type', 1)->get();
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
            //  Fix validation keys
            $request->validate([
                'name' => 'required|string|max:255',
                'designation' => 'required|string|max:255',
                'email' => 'required|email|unique:users,email',
                'password' => 'required|string|min:6',
                'employee_id' => [
                    'required',
                    'string',
                    Rule::unique('users', 'employee_id')->where('entiti_id', $request->entiti_id)
                ],
                'entiti_id' => 'required|integer|exists:entitis,id',
                'department_id' => 'required|integer|exists:departments,id',
                'loa' => 'required|numeric|min:0',
                'signature' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:2048',
                'status' => ['required', Rule::in(['Active', 'Inactive', 'Away'])],
                'roles' => 'sometimes|array',
            ]);

            // Entity and department validation
            $department = Department::find($request->department_id);
            $entity = Entiti::find($request->entiti_id);

            if (!$department || !$entity || $department->entiti_id != $entity->id) {
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

            // Create user first
            $user = User::create([
                'name' => $request->name,
                'designation' => $request->designation,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'employee_id' => $request->employee_id,
                'entiti_id' => $request->entiti_id,
                'department_id' => $request->department_id,
                'loa' => $request->loa,
                'signature' => '',
                'status' => $request->status,
            ]);

            //  Upload signature if present
            if ($request->hasFile('signature')) {
                $file = $request->file('signature');
                $extension = $file->getClientOriginalExtension(); //  fixed
                $filename = 'uid_' . $user->id . '_signature.' . $extension;

                $path = $file->storeAs('upload/signature', $filename, 'public');

                $user->signature = $path;
                $user->save();
            }

            // Sync roles
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


    public function nextEmployeeId(Request $request)
    {
        try {
            $entityId = $request->query('entity_id');

            if (!$entityId) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'entity_id is required'
                ], 400);
            }

            $employeeIds = User::where('entiti_id', $entityId)
                ->pluck('employee_id')
                ->map(function ($id) {
                    return intval(substr($id, 3)); // EMP0001 → 1
                })
                ->sort()
                ->values();

            $nextNumber = 1;
            foreach ($employeeIds as $num) {
                if ($num == $nextNumber) {
                    $nextNumber++;
                } else {
                    break;
                }
            }

            $nextEmployeeId = 'EMP' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);

            return response()->json([
                'status' => 'success',
                'message' => 'Next employee ID retrieved successfully',
                'data' => [
                    'employee_id' => $nextEmployeeId
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to generate employee ID',
                'error' => $e->getMessage()
            ], 500);
        }
    }



    // public function nextEmployeeId()
    // {
    //     try {
    //         // Get all employee IDs starting with "EMP" and followed by digits
    //         $lastId = User::where('employee_id', 'LIKE', 'EMP%')
    //             ->selectRaw("MAX(CAST(SUBSTRING(employee_id, 4) AS UNSIGNED)) as max_id")
    //             ->value('max_id');

    //         $nextNumber = $lastId ? $lastId + 1 : 1;

    //         $nextEmployeeId = 'EMP' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);

    //         return response()->json([
    //             'status' => 'success',
    //             'message' => 'Next employee ID retrieved successfully',
    //             'data' => [
    //                 'employee_id' => $nextEmployeeId
    //             ]
    //         ], 200);
    //     } catch (\Exception $e) {
    //         return response()->json([
    //             'status' => 'error',
    //             'message' => 'Failed to retrieve next employee ID',
    //             'error' => $e->getMessage()
    //         ], 500);
    //     }
    // }


    // public function nextEmployeeId()
    // {
    //     try {
    //         // Fetch all employee_ids starting with 'EMP' in ascending order
    //         $employeeIds = User::where('employee_id', 'LIKE', 'EMP%')
    //             ->pluck('employee_id')
    //             ->map(function ($id) {
    //                 return intval(substr($id, 3)); // extract numeric part
    //             })
    //             ->sort()
    //             ->values();

    //         // Find the first missing number in the sequence
    //         $nextNumber = 1;
    //         foreach ($employeeIds as $number) {
    //             if ($number == $nextNumber) {
    //                 $nextNumber++;
    //             } else {
    //                 break; // found a gap
    //             }
    //         }

    //         $nextEmployeeId = 'EMP' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);

    //         return response()->json([
    //             'status' => 'success',
    //             'message' => 'Next employee ID retrieved successfully',
    //             'data' => [
    //                 'employee_id' => $nextEmployeeId
    //             ]
    //         ], 200);
    //     } catch (\Exception $e) {
    //         return response()->json([
    //             'status' => 'error',
    //             'message' => 'Failed to retrieve next employee ID',
    //             'error' => $e->getMessage()
    //         ], 500);
    //     }
    // }


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
            // Fetch the user with their roles and permissions
            $user = User::with('roles.permissions')->findOrFail($id);

            // Manually extract permissions from the user's roles
            $permissions = $user->roles->flatMap(function ($role) {
                return $role->permissions->pluck('name');
            })->unique()->values(); // Ensure unique permissions and reset array keys

            return response()->json([
                'status' => 'success',
                'message' => 'User retrieved successfully',
                'data' => $user, // User data
                'permissions' => $permissions, // Permissions of the user
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'User not found',
            ], 404);
        } catch (QueryException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve user',
                'error' => $e->getMessage(),
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
                'designation' => 'sometimes|required|string|max:255',
                'email' => ['sometimes', 'required', 'email', Rule::unique('users')->ignore($id)],
                'password' => 'sometimes|required|string|min:6',
                'employee_id' => ['sometimes', 'required', 'string', Rule::unique('users')->ignore($id)],
                'entiti_id' => 'sometimes|required|integer|exists:entitis,id',
                'department_id' => 'sometimes|required|integer|exists:departments,id',
                'loa' => 'sometimes|required|numeric|min:0',
                'signature' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:2048',
                'status' => ['sometimes', 'required', Rule::in(['Active', 'Inactive', 'Away'])],
                'roles' => 'sometimes|array',
            ]);

            $user = User::findOrFail($id);

            // Determine department and entity for validation
            $department_id = $request->department_id ?? $user->department_id;
            $entiti_id = $request->entiti_id ?? $user->entiti_id;

            $department = Department::find($department_id);
            $entity = Entiti::find($entiti_id);

            if (!$department || !$entity || $department->entiti_id != $entity->id) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'The selected department does not belong to the specified entity.'
                ], 400);
            }

            // Check department budget if LOA is being updated
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

            // Prepare data to update
            $data = $request->only([
                'name',
                'designation',
                'email',
                'employee_id',
                'entiti_id',
                'department_id',
                'loa',
                'status'
            ]);

            if ($request->has('password')) {
                $data['password'] = Hash::make($request->password);
            }

            // Handle signature file upload
            if ($request->hasFile('signature')) {
                $file = $request->file('signature');
                $extension = $file->getClientOriginalExtension();
                $filename = 'uid_' . $user->id . '_signature.' . $extension;
                $path = $file->storeAs('upload/signature', $filename, 'public');

                // Optional: delete old file
                if ($user->signature) {
                    Storage::disk('public')->delete($user->signature);
                }

                $data['signature'] = $path;
            }

            // Update user
            $user->update($data);

            // Sync roles
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



    public function search(Request $request)
    {
        try {
            $request->validate([
                'keyword' => 'required|string'
            ]);

            $keyword = trim($request->keyword);

            $users = User::with('roles')
                ->where('user_type', 1)
                ->where(function ($q) use ($keyword) {
                    $q->whereRaw('LOWER(employee_id) LIKE ?', ['%' . strtolower($keyword) . '%'])
                        ->orWhereRaw('LOWER(name) LIKE ?', ['%' . strtolower($keyword) . '%'])
                        ->orWhereRaw('LOWER(email) LIKE ?', ['%' . strtolower($keyword) . '%']);
                })
                ->get();

            if ($users->isEmpty()) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'No matching users found',
                    'data' => []
                ], 200);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Users found successfully',
                'data' => $users
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
                'message' => 'Database error',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
