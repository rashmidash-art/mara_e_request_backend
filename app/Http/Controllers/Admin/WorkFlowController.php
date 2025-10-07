<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WorkFlow;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class WorkFlowController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $workFlows = WorkFlow::all();
        return response()->json([
            'status' => 'success',
            'message' => 'Workflows retrieved successfully',
            'data' => $workFlows
        ], 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            $request->validate([
                'name' => 'required|string|unique:work_flows,name|max:255',
                'status' => 'nullable|integer|in:0,1'
            ], [
                'name.required' => 'Workflow name is required.',
                'name.unique' => 'Workflow name already exists.',
                'name.max' => 'Workflow name cannot exceed 255 characters.',
                'status.integer' => 'Status must be an integer.',
                'status.in' => 'Status must be 0 (Active) or 1 (Inactive).'
            ]);

            $workFlow = WorkFlow::create([
                'name' => $request->name,
                'status' => $request->status ?? 0
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Workflow Created successfully',
                'data' => $workFlow
            ], 200);

        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->errors()
            ], 500);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Server error occurred'
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $workFlow = WorkFlow::find($id);
        if (!$workFlow) {
            return response()->json([
                'status' => 'error',
                'message' => 'Workflow not found'
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Data found successfully',
            'data' => $workFlow
        ], 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $workFlow = WorkFlow::find($id);
        if (!$workFlow) {
            return response()->json([
                'status' => 'error',
                'message' => 'Workflow not found'
            ], 404);
        }

        try {
            $request->validate([
                'name' => 'sometimes|required|string|max:255|unique:work_flows,name,' . $workFlow->id,
                'status' => 'sometimes|integer|in:0,1'
            ], [
                'name.required' => 'Workflow name is required.',
                'name.unique' => 'Workflow name already exists.',
                'name.max' => 'Workflow name cannot exceed 255 characters.',
                'status.integer' => 'Status must be an integer.',
                'status.in' => 'Status must be 0 (Active) or 1 (Inactive).'
            ]);

            $workFlow->update($request->only('name', 'status'));

            return response()->json([
                'status' => 'success',
                'message' => 'Updated Successfully',
                'data' => $workFlow
            ], 200);

        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->errors()
            ], 500);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Server error occurred'
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $workFlow = WorkFlow::find($id);
        if (!$workFlow) {
            return response()->json([
                'status' => 'error',
                'message' => 'Workflow not found'
            ], 404);
        }

        try {
            $workFlow->delete();
            return response()->json([
                'status' => 'success',
                'message' => 'Deleted Successfully'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Server error occurred'
            ], 500);
        }
    }
}
