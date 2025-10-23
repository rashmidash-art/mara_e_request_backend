<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WorkFlowType;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class WorkFlowTypeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $workFlows = WorkFlowType::all();
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
                'name.required' => 'Work Flow Type name is required.',
                'name.unique' => 'Work Flow Type name already exists.',
                'name.max' => 'Work Flow Type name cannot exceed 255 characters.',
                'status.integer' => 'Status must be an integer.',
                'status.in' => 'Status must be 0 (Active) or 1 (Inactive).'
            ]);

            $workflowtype= WorkFlowType::create([
                'name' => $request->name,
                'status' => $request->status ?? 0
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'WorkFlowTypeCreated successfully',
                'data' => $workflowtype
            ], 200);

        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->errors(),
                'error'=>$e -> getMessage()
            ], 500);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Server error occurred',
                'error'=>$e -> getMessage()

            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $workflowtype= WorkFlowType::find($id);
        if (!$workflowtype) {
            return response()->json([
                'status' => 'error',
                'message' => 'Work Flow Type not found',

            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Data found successfully',
            'data' => $workflowtype
        ], 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $workflowtype= WorkFlowType::find($id);
        if (!$workflowtype) {
            return response()->json([
                'status' => 'error',
                'message' => 'Work Flow Type not found'
            ], 404);
        }

        try {
            $request->validate([
                'name' => 'sometimes|required|string|max:255|unique:work_flows,name,' . $workflowtype->id,
                'status' => 'sometimes|integer|in:0,1'
            ], [
                'name.required' => 'Work Flow Type name is required.',
                'name.unique' => 'Work Flow Type name already exists.',
                'name.max' => 'Work Flow Type name cannot exceed 255 characters.',
                'status.integer' => 'Status must be an integer.',
                'status.in' => 'Status must be 0 (Active) or 1 (Inactive).'
            ]);

            $workflowtype->update($request->only('name', 'status'));

            return response()->json([
                'status' => 'success',
                'message' => 'Updated Successfully',
                'data' => $workflowtype
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
        $workflowtype= WorkFlowType::find($id);
        if (!$workflowtype) {
            return response()->json([
                'status' => 'error',
                'message' => 'WorkFlowTypenot found'
            ], 404);
        }

        try {
            $workflowtype->delete();
            return response()->json([
                'status' => 'success',
                'message' => 'Deleted Successfully'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Server error occurred',
                'error'=>$e->getMessage()
            ], 500);
        }
    }
}
