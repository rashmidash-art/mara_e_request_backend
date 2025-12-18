<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Entiti;
use App\Models\Manager;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class DeprtmentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {

            $user = $request->user();
            if ($user instanceof User && $user->user_type == 0) {
                $departments = Department::all();
            } elseif ($user instanceof Entiti) {
                $departments = Department::where('entiti_id', $user->id)->get();
            } elseif ($user instanceof User) {
                // If you want normal users to see all
                $departments = Department::all();
                // OR restrict by permissions if needed
                // $departments = Department::whereIn('id', $user->departments()->pluck('department_id'))->get();
            }

            // $departments = Department::all();
            return response()->json([
                'status' => 'success',
                'message' => 'Departments retrieved successfully',
                'data' => $departments,
            ], 200);
        } catch (QueryException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve departments',
            ], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $user = Auth::user();
            $entitiId = $user->type === 'entity'
                ? $user->entiti_id
                : $request->entiti_id;
            $request->validate([
                'entiti_id' => 'required|integer|exists:entitis,id',
                'name' => [
                    'required',
                    'string',
                    'max:255',
                    Rule::unique('departments')->where(
                        fn ($q) => $q->where('entiti_id', $request->entiti_id)
                    ),
                ],

                'department_code' => [
                    'required',
                    'string',
                    'max:50',
                    Rule::unique('departments')->where(
                        fn ($q) => $q->where('entiti_id', $request->entiti_id)
                    ),
                ],

                'enable_cost_center' => 'nullable|integer|in:0,1',
                'description' => 'nullable|string',
                'budget' => 'nullable|numeric|min:0',
                'status' => 'nullable|integer|in:0,1',
            ]);

            $entity = Entiti::findOrFail($entitiId);

            $currentDepartmentBudget = Department::where('entiti_id', $entitiId)
                ->sum('budget');

            $requestedBudget = $request->budget ?? 0;

            if ($requestedBudget > $entity->budget) {
                return response()->json([
                    'status' => 'error',
                    'message' => "Department budget cannot exceed entity budget ({$entity->budget}).",
                ], 400);
            }

            if (($currentDepartmentBudget + $requestedBudget) > $entity->budget) {
                return response()->json([
                    'status' => 'error',
                    'message' => "Total department budgets exceed entity budget ({$entity->budget}).",
                ], 400);
            }
            $department = Department::create([
                'entiti_id' => $entitiId,
                'manager_id' => null,
                'name' => $request->name,
                'department_code' => $request->department_code,
                'bc_dimention_value' => Department::generateBcDimension($request->department_code),
                'enable_cost_center' => $request->enable_cost_center ?? 0,
                'budget' => $requestedBudget,
                'description' => $request->description,
                'status' => $request->status ?? 0,
            ]);

            if ($request->user_id) {

                $user = User::find($request->user_id);

                $manager = Manager::create([
                    'user_id' => $request->user_id,
                    'entiti_id' => $entitiId,
                    'department_id' => $department->id,
                    'employee_id' => $user->employee_id ?? null,
                    'name' => $user->name ?? null,
                    'status' => 0,
                ]);

                $department->update([
                    'manager_id' => $manager->id,
                ]);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Department created successfully',
                'data' => $department,
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'validation_error',
                'errors' => $e->errors(),
            ], 422);
        } catch (QueryException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    // Show a single department
    public function show($id)
    {
        try {
            $department = Department::find($id);
            if (! $department) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Department not found',
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Department details retrieved successfully',
                'data' => $department,
            ], 200);
        } catch (QueryException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve department details',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // Update a department
    public function update(Request $request, $id)
    {
        try {

            $department = Department::find($id);

            if (! $department) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Department not found',
                ], 404);
            }

            // Validation
            $request->validate([
                'entiti_id' => 'sometimes|required|integer|exists:entitis,id',

                'name' => [
                    'sometimes',
                    'required',
                    'string',
                    'max:255',
                    Rule::unique('departments')
                        ->where(fn ($q) => $q->where('entiti_id', $request->entiti_id ?? $department->entiti_id)
                        )
                        ->ignore($department->id),
                ],

                'department_code' => [
                    'sometimes',
                    'required',
                    'string',
                    'max:50',
                    Rule::unique('departments', 'department_code')
                        ->where(fn ($q) => $q->where('entiti_id', $request->entiti_id ?? $department->entiti_id)
                        )
                        ->ignore($department->id),
                ],

                'enable_cost_center' => 'sometimes|integer|in:0,1',
                'budget' => 'sometimes|numeric|min:0',
                'description' => 'nullable|string',
                'status' => 'sometimes|integer|in:0,1',
                'manager_id' => 'sometimes|nullable|integer|exists:managers,id',
            ], [
                'name.unique' => 'Department name already exists for this entity.',
                'department_code.unique' => 'Department code already exists.',
            ]);

            // Determine the entity
            $entityId = $request->entiti_id ?? $department->entiti_id;
            $entity = Entiti::findOrFail($entityId);

            // Budget validations
            $requestedBudget = $request->budget ?? $department->budget;

            $currentDepartmentBudget = Department::where('entiti_id', $entityId)
                ->where('id', '!=', $department->id)
                ->sum('budget');

            if ($requestedBudget > $entity->budget) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Department budget cannot exceed entity budget of '.$entity->budget,
                ], 400);
            }

            if (($currentDepartmentBudget + $requestedBudget) > $entity->budget) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Total department budgets ('
                        .($currentDepartmentBudget + $requestedBudget)
                        .') cannot exceed entity budget of '
                        .$entity->budget,
                ], 400);
            }
            // Regenerate BC-Dimension only if department_code changed
            $newBcDimension = $department->bc_dimention_value;
            if ($request->department_code && $request->department_code !== $department->department_code) {
                $newBcDimension = Department::generateBcDimension($request->department_code);
            }

            // Perform Update
            $department->update([
                'entiti_id' => $entityId,
                'manager_id' => $request->manager_id ?? $department->manager_id,
                'name' => $request->name ?? $department->name,
                'department_code' => $request->department_code ?? $department->department_code,
                'bc_dimention_value' => $newBcDimension,
                'enable_cost_center' => $request->enable_cost_center ?? $department->enable_cost_center,
                'budget' => $requestedBudget,
                'description' => $request->description ?? $department->description,
                'status' => $request->status ?? $department->status,
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Department updated successfully',
                'data' => $department,
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->errors(),
            ], 422);
        } catch (QueryException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    // Delete a department
    public function destroy($id)
    {
        try {
            $department = Department::find($id);
            if (! $department) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Department not found',
                ], 404);
            }

            $department->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Department deleted successfully',
            ], 200);
        } catch (QueryException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete department',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // public function getByEntity($id)
    // {
    //     $departments = Department::where('entiti_id', $id)->get();

    //     return response()->json([
    //         'status' => 'success',
    //         'departments' => $departments,
    //     ]);
    // }

    public function getByEntity($id)
    {
        try {
            $entity = Entiti::findOrFail($id);
            // Get departments for this entity
            $departments = Department::where('entiti_id', $id)->get();
            $allOption = [
                'id' => 0,
                'name' => 'All Departments',
                'budget' => $entity->budget, // entity LOA used here
                'entiti_id' => $entity->id,
                'bc_dimention_value' => null,
                'enable_cost_center' => 0,
                'description' => null,
                'status' => 1,
                'manager_id' => null,
            ];

            $departmentsArray = $departments->toArray();
            array_unshift($departmentsArray, $allOption);

            return response()->json([
                'status' => 'success',
                'departments' => $departmentsArray,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve departments',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function getUserbyDepartment($id)
    {
        $users = User::where('department_id', $id)->get();

        return response()->json([
            'status' => 'success',
            'users' => $users,
        ]);
    }
}
