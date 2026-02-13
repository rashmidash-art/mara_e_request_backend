<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WorkFlow;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;

class WorkFlowController extends Controller
{
    /**
     * Display all workflows.
     */
    public function index()
    {
        try {
            // $workflows = WorkFlow::all();
            $workflows = WorkFlow::orderBy('id', 'desc')->get();

            return response()->json([
                'status' => 'success',
                'message' => 'Workflows retrieved successfully',
                'data' => $workflows,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve workflows',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Store a new workflow.
     */
    public function store(Request $request)
    {
        // Validate input
        $validated = $request->validate([
            'entity_id' => 'nullable|integer|exists:entitis,id',
            'categori_id' => 'nullable|integer|exists:categories,id',
            'request_type_id' => 'nullable|integer|exists:request_types,id',
            'name' => 'nullable|string|max:255',
            'steps' => 'nullable|integer',
            'status' => 'nullable|integer',
            'description' => 'nullable|string',
        ]);

        try {
            $exists = WorkFlow::where('entity_id', $validated['entity_id'])
                ->where('categori_id', $validated['categori_id'])
                ->where('request_type_id', $validated['request_type_id'])
                ->exists();

            if ($exists) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'A workflow already exists for this Entity + Category + Request Type combination.',
                ], 422);
            }

            $lastWorkflow = WorkFlow::latest('id')->first();
            $nextId = $lastWorkflow ? $lastWorkflow->id + 1 : 1;
            $validated['workflow_id'] = 'WF'.str_pad($nextId, 3, '0', STR_PAD_LEFT);

            $workflow = WorkFlow::create($validated);

            return response()->json([
                'status' => 'success',
                'message' => 'Workflow created successfully',
                'data' => $workflow,
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create workflow',
                'error' => $e->getMessage(),
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
                'data' => $workflow,
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Workflow not found',
                'error' => $e->getMessage(),
            ], 401);
        }
    }

    /**
     * Update a workflow.
     */
    public function update(Request $request, string $id)
    {
        $validated = $request->validate([
            'entity_id' => 'nullable|integer|exists:entitis,id',
            'categori_id' => 'nullable|integer|exists:categories,id',
            'request_type_id' => 'nullable|integer|exists:request_types,id',
            'name' => 'nullable|string|max:255',
            'steps' => 'nullable|integer',
            'status' => 'nullable|integer',
            'description' => 'nullable|string',
        ]);

        try {
            $workflow = WorkFlow::findOrFail($id);

            // Use existing values if not provided
            $entityId = $validated['entity_id'] ?? $workflow->entity_id;
            $categoryId = $validated['categori_id'] ?? $workflow->categori_id;
            $requestTypeId = $validated['request_type_id'] ?? $workflow->request_type_id;

            // Check duplicate (excluding current workflow)
            $exists = WorkFlow::where('entity_id', $entityId)
                ->where('categori_id', $categoryId)
                ->where('request_type_id', $requestTypeId)
                ->where('id', '!=', $workflow->id)
                ->exists();

            if ($exists) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'A workflow already exists for this Entity + Category + Request Type combination.',
                ], 422);
            }

            $workflow->update($validated);

            return response()->json([
                'status' => 'success',
                'message' => 'Workflow updated successfully',
                'data' => $workflow,
            ], 200);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Workflow not found',
                'error' => $e->getMessage(),
            ], 404);
        } catch (QueryException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update workflow',
                'error' => $e->getMessage(),
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

            // Delete all related steps, roles, and escalations
            $workflow->steps()->delete();
            $workflow->roles()->delete();
            $workflow->escalations()->delete();

            // Then delete the workflow itself
            $workflow->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Workflow and all related steps, roles, and escalations deleted successfully',
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Workflow not found',
                'error' => $e->getMessage(),
            ], 404);
        } catch (QueryException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete workflow',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // app/Http/Controllers/WorkflowController.php
    public function getWorkflowByTypeAndCategory(Request $request)
    {
        $request->validate([
            'request_type_id' => 'required|integer',
            'category_id' => 'required|integer',
        ]);

        $workflow = WorkFlow::where('request_type_id', $request->request_type_id)
            ->where('categori_id', $request->category_id)
            ->first();

        if (! $workflow) {
            return response()->json([
                'status' => 'error',
                'message' => 'Workflow not found for the selected request type & category.',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $workflow,
        ]);
    }

    public function getWorkflowByEntity($id)
    {

        $data = WorkFlow::where('entity_id', $id)->get();  // filter by entity_id

        return response()->json([
            'status' => 'success',
            'steps' => $data,
            'data' => $data,
        ]);
    }
}
