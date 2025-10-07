<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Entiti;
use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;

class DeprtmentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        try {
            $departments = Department::all();
            return response()->json([
                'status' => 'success',
                'message' => 'Departments retrieved successfully',
                'data' => $departments
            ], 200);
        } catch (QueryException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve departments'
            ], 500);
        }
    }

    // Create a new department
    public function store(Request $request)
    {
        try {
            $request->validate([
                'entiti_id' => 'required|integer|exists:entitis,id',
                'manager_id' => 'required|integer|exists:managers,id',
                'name' => 'required|string|max:255|unique:departments,name',
                'department_code' => 'required|string|max:50|unique:departments,department_code',
                'bc_dimention_value' => 'required|string|max:255',
                'enable_cost_center' => 'nullable|integer|in:0,1',
                'work_flow_id' => 'required|integer|exists:work_flows,id',
                'description' => 'nullable|string',
                'budget' => 'nullable|numeric|min:0',
                'status' => 'nullable|integer|in:0,1'
            ], [
                'entiti_id.required' => 'Entity ID is required.',
                'entiti_id.exists' => 'Entity does not exist.',
                'manager_id.required' => 'Manager ID is required.',
                'manager_id.exists' => 'Manager does not exist.',
                'name.required' => 'Department name is required.',
                'name.unique' => 'Department name already exists.',
                'department_code.required' => 'Department code is required.',
                'department_code.unique' => 'Department code already exists.',
                'bc_dimention_value.required' => 'BC dimension value is required.',
                'enable_cost_center.in' => 'Enable cost center must be 0 or 1.',
                'work_flow_id.required' => 'Workflow ID is required.',
                'work_flow_id.exists' => 'Workflow does not exist.',
                'budget.numeric' => 'Budget must be a number.',
                'budget.min' => 'Budget cannot be negative.',
                'description.string' => 'Description must be a valid text.',
                'status.in' => 'Status must be 0 (Active) or 1 (Inactive).'
            ]);
            $entity = Entiti::findOrFail($request->entiti_id);

            $currentDepartmentBudget = Department::where('entiti_id', $request->entiti_id)->sum('budget');

            $requestedBudget = $request->budget ?? 0;

            if ($requestedBudget > $entity->budget) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Department budget cannot exceed the entity budget of ' . $entity->budget
                ], 400);
            }

            if (($currentDepartmentBudget + $requestedBudget) > $entity->budget) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Total department budgets (' . ($currentDepartmentBudget + $requestedBudget) . ') cannot exceed entity budget of ' . $entity->budget
                ], 400);
            }

            $department = Department::create([
                'entiti_id' => $request->entiti_id,
                'manager_id' => $request->manager_id,
                'name' => $request->name,
                'department_code' => $request->department_code,
                'bc_dimention_value' => $request->bc_dimention_value,
                'enable_cost_center' => $request->enable_cost_center ?? 0,
                'work_flow_id' => $request->work_flow_id,
                'budget' => $requestedBudget,
                'description' => $request->description,
                'status' => $request->status ?? 0
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Department created successfully',
                'data' => $department
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->errors()
            ], 422);
        } catch (QueryException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }


    // Show a single department
    public function show($id)
    {
        try {
            $department = Department::find($id);
            if (!$department) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Department not found'
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Department details retrieved successfully',
                'data' => $department
            ], 200);
        } catch (QueryException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve department details'
            ], 500);
        }
    }

    // Update a department
    public function update(Request $request, $id)
    {
        try {
            $department = Department::find($id);
            if (!$department) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Department not found'
                ], 404);
            }

            $request->validate([
                'entiti_id' => 'sometimes|required|integer|exists:entitis,id',
                'manager_id' => 'sometimes|required|integer|exists:managers,id',
                'name' => 'sometimes|required|string|max:255|unique:departments,name,' . $department->id,
                'department_code' => 'sometimes|required|string|max:50|unique:departments,department_code,' . $department->id,
                'bc_dimention_value' => 'sometimes|required|string|max:255',
                'enable_cost_center' => 'sometimes|integer|in:0,1',
                'work_flow_id' => 'sometimes|required|integer|exists:work_flows,id',
                'budget' => 'sometimes|numeric|min:0',
                'description' => 'nullable|string',
                'status' => 'sometimes|integer|in:0,1'
            ], [
                'entiti_id.exists' => 'Entity does not exist.',
                'manager_id.exists' => 'Manager does not exist.',
                'name.unique' => 'Department name already exists.',
                'department_code.unique' => 'Department code already exists.',
                'enable_cost_center.in' => 'Enable cost center must be 0 or 1.',
                'work_flow_id.exists' => 'Workflow does not exist.',
                'budget.numeric' => 'Budget must be a number.',
                'budget.min' => 'Budget cannot be negative.',
                'description.string' => 'Description must be a valid text.',
                'status.in' => 'Status must be 0 (Active) or 1 (Inactive).'
            ]);

            $entityId = $request->entiti_id ?? $department->entiti_id;
            $entity   = Entiti::findOrFail($entityId);

            $requestedBudget = $request->budget ?? $department->budget;

            $currentDepartmentBudget = Department::where('entiti_id', $entityId)
                ->where('id', '!=', $department->id)
                ->sum('budget');

            if ($requestedBudget > $entity->budget) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Department budget cannot exceed entity budget of ' . $entity->budget
                ], 400);
            }

            if (($currentDepartmentBudget + $requestedBudget) > $entity->budget) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Total department budgets (' . ($currentDepartmentBudget + $requestedBudget) . ') cannot exceed entity budget of ' . $entity->budget
                ], 400);
            }

            $department->update([
                'entiti_id' => $request->entiti_id ?? $department->entiti_id,
                'manager_id' => $request->manager_id ?? $department->manager_id,
                'name' => $request->name ?? $department->name,
                'department_code' => $request->department_code ?? $department->department_code,
                'bc_dimention_value' => $request->bc_dimention_value ?? $department->bc_dimention_value,
                'enable_cost_center' => $request->enable_cost_center ?? $department->enable_cost_center,
                'work_flow_id' => $request->work_flow_id ?? $department->work_flow_id,
                'budget' => $requestedBudget,
                'description' => $request->description ?? $department->description,
                'status' => $request->status ?? $department->status
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Department updated successfully',
                'data' => $department
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->errors()
            ], 422);
        } catch (QueryException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }


    // Delete a department
    public function destroy($id)
    {
        try {
            $department = Department::find($id);
            if (!$department) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Department not found'
                ], 404);
            }

            $department->delete();
            return response()->json([
                'status' => 'success',
                'message' => 'Department deleted successfully'
            ], 200);
        } catch (QueryException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete department'
            ], 500);
        }
    }
}
