<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WorkFlow;
use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\Rule;

class WorkFlowController extends Controller
{
    /**
     * Display all workflows.
     */
    public function index()
    {
        try {
            $workflows = WorkFlow::all();

            return response()->json([
                'status' => 'success',
                'message' => 'Workflows retrieved successfully',
                'data' => $workflows
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve workflows',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a new workflow.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'categori_id' => 'nullable|integer',
            'name' => 'nullable|string|max:255',
            'steps' => 'nullable|integer',
            'status' => 'nullable|integer',
            'description' => 'nullable|string',
        ]);

        try {
            // Generate workflow_id automatically: WF001, WF002...
            $lastWorkflow = WorkFlow::latest('id')->first();
            $nextId = $lastWorkflow ? $lastWorkflow->id + 1 : 1;
            $workflowId = 'WF' . str_pad($nextId, 3, '0', STR_PAD_LEFT);

            $validated['workflow_id'] = $workflowId;

            $workflow = WorkFlow::create($validated);

            return response()->json([
                'status' => 'success',
                'message' => 'Workflow created successfully',
                'data' => $workflow
            ], 200);
        } catch (QueryException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create workflow',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    /**
     * Display a specific workflow.
     */
    public function show(string $id)
    {
        try {
            $workflow = WorkFlow::findOrFail($id);

            return response()->json([
                'status' => 'success',
                'message' => 'Workflow retrieved successfully',
                'data' => $workflow
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Workflow not found',
                'error' => $e->getMessage()
            ], 401);
        }
    }

    /**
     * Update a workflow.
     */
    public function update(Request $request, string $id)
    {
        $validated = $request->validate([
            'categori_id' => 'nullable|integer',
            'name' => 'nullable|string|max:255',
            'steps' => 'nullable|integer',
            'status' => 'nullable|integer',
            'description' => 'nullable|string',
        ]);

        try {
            $workflow = WorkFlow::findOrFail($id);

            // Only update the allowed fields
            $workflow->update($validated);

            return response()->json([
                'status' => 'success',
                'message' => 'Workflow updated successfully',
                'data' => $workflow
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Workflow not found',
                'error' => $e->getMessage()
            ], 401);
        } catch (QueryException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update workflow',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    /**
     * Delete a workflow.
     */
    public function destroy(string $id)
    {
        try {
            $workflow = WorkFlow::findOrFail($id);
            $workflow->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Workflow deleted successfully'
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Workflow not found',
                'error' => $e->getMessage()
            ], 401);
        } catch (QueryException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete workflow',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
