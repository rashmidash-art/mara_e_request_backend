<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Manager;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;

class ManagerController extends Controller
{
    // List all managers
    public function index()
    {
        try {
            $managers = Manager::all();
            return response()->json([
                'status' => 'success',
                'message' => 'Managers retrieved successfully',
                'data' => $managers
            ], 200);
        } catch (QueryException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve managers'
            ], 500);
        }
    }

    // Create a new manager
    public function store(Request $request)
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255',
                'employee_id' => 'required|string|max:255|unique:managers,employee_id',
                'status' => 'nullable|integer|in:0,1'
            ], [
                'name.required' => 'Manager name is required.',
                'employee_id.required' => 'employee_id name is required.',
                'employee_id.unique' => 'employee_id name already exists.',
                'name.max' => 'Manager name cannot exceed 255 characters.',
                'employee_id.max' => 'Manager employee_id cannot exceed 255 characters.',
                'status.integer' => 'Status must be an integer.',
                'status.in' => 'Status must be 0 (Active) or 1 (Inactive).'
            ]);

            $manager = Manager::create([
                'name' => $request->name,
                'employee_id'=> $request->employee_id,
                'status' => $request->status ?? 0
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Manager created successfully',
                'data' => $manager
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->errors()
            ], 500);
        } catch (QueryException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create manager'
            ], 500);
        }
    }

    // Show a single manager
    public function show($id)
    {
        try {
            $manager = Manager::find($id);
            if (!$manager) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Manager not found'
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Manager details retrieved successfully',
                'data' => $manager
            ], 200);
        } catch (QueryException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve manager details'
            ], 500);
        }
    }

    // Update a manager
    public function update(Request $request, $id)
    {
        try {
            $manager = Manager::find($id);
            if (!$manager) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Manager not found'
                ], 404);
            }

            $request->validate([
                'name' => 'sometimes|required|string|max:255',
                'employee_id' => 'sometimes|required|string|max:255|unique:managers,employee_id,' . $manager->id,
                'status' => 'sometimes|integer|in:0,1'
            ], [
                'name.required' => 'Manager name is required.',
                'employee_id.required' => 'Employee ID is required.',
                'employee_id.unique' => 'Employee ID already exists.',
                'name.max' => 'Manager name cannot exceed 255 characters.',
                'employee_id.max' => 'Employee ID cannot exceed 255 characters.',
                'status.integer' => 'Status must be an integer.',
                'status.in' => 'Status must be 0 (Active) or 1 (Inactive).'
            ]);

            $manager->update($request->only('name','employee_id', 'status'));

            return response()->json([
                'status' => 'success',
                'message' => 'Manager updated successfully',
                'data' => $manager
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->errors()
            ], 500);
        } catch (QueryException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update manager'
            ], 500);
        }
    }

    // Delete a manager
    public function destroy($id)
    {
        try {
            $manager = Manager::find($id);
            if (!$manager) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Manager not found'
                ], 404);
            }

            $manager->delete();
            return response()->json([
                'status' => 'success',
                'message' => 'Manager deleted successfully'
            ], 200);
        } catch (QueryException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete manager'
            ], 500);
        }
    }
}
