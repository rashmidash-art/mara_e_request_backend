<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WorkflowStep;
use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class WorkFlowStepsController extends Controller
{


    protected function updateStepCount(int $workflowId)
    {
        // Count the steps for the workflow
        $stepCount = WorkflowStep::where('workflow_id', $workflowId)->count();

        // Update the 'step' column in the work_flows table
        DB::table('work_flows')->where('id', $workflowId)->update(['steps' => $stepCount]);
    }
    /**
     * Display a listing of all workflow steps.
     */
    public function index()
    {
        try {
            $steps = WorkflowStep::orderBy('order_id', 'asc')->get();

            return response()->json([
                'status' => 'success',
                'message' => 'Workflow steps retrieved successfully',
                'data' => $steps
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve workflow steps',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created workflow step.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'workflow_id' => 'required|integer|exists:work_flows,id',
            'order_id' => [
                'nullable',
                'integer',
                'min:1',
                Rule::unique('workflow_steps')->where(function ($query) use ($request) {
                    return $query->where('workflow_id', $request->workflow_id);
                }),
            ],
            'name' => 'required|string|max:255|unique:workflow_steps,name',
            'form_type' => 'nullable|string|max:255',
            'sla_hour' => 'nullable|integer',
            'description' => 'nullable|string',
            'escalation' => 'nullable|string|max:255',
            'status' => 'nullable|integer|in:0,1'
        ]);

        try {
            // Auto-assign order_id if not provided
            if (empty($validated['order_id'])) {
                $maxOrder = WorkflowStep::where('workflow_id', $validated['workflow_id'])->max('order_id');
                $validated['order_id'] = $maxOrder ? $maxOrder + 1 : 1;
            }

            $step = WorkflowStep::create($validated);
            $this->updateStepCount($validated['workflow_id']);
            return response()->json([
                'status' => 'success',
                'message' => 'Workflow step created successfully',
                'data' => $step
            ], 200);
        } catch (QueryException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create workflow step',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    /**
     * Display a specific workflow step.
     */
    public function show(string $id)
    {
        try {
            $step = WorkflowStep::findOrFail($id);

            return response()->json([
                'status' => 'success',
                'message' => 'Workflow step retrieved successfully',
                'data' => $step
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Workflow step not found',
                'error' => $e->getMessage()
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve workflow step',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update a workflow step.
     */
    public function update(Request $request, $id)
    {
        try {
            $step = WorkflowStep::findOrFail($id);

            $validated = $request->validate([
                'workflow_id' => 'required|integer|exists:work_flows,id',
                'order_id' => [
                    'nullable',
                    'integer',
                    'min:1',
                    Rule::unique('workflow_steps')
                        ->where(fn($query) => $query->where('workflow_id', $request->workflow_id))
                        ->ignore($id),
                ],
                'name' => [
                    'required',
                    'string',
                    'max:255',
                    Rule::unique('workflow_steps')->ignore($id),
                ],
                'form_type' => 'nullable|string|max:255',
                'sla_hour' => 'nullable|integer',
                'description' => 'nullable|string',
                'escalation' => 'nullable|string|max:255',
                'status' => 'nullable|integer|in:0,1'
            ]);

            $step->update($validated);

            return response()->json([
                'status' => 'success',
                'message' => 'Workflow step updated successfully',
                'data' => $step
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Workflow step not found',
                'error' => $e->getMessage()
            ], 404);
        } catch (QueryException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update workflow step',
                'error' => $e->getMessage()
            ], 500);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unexpected error occurred',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a workflow step.
     */
    public function destroy(string $id)
    {
        try {
            $step = WorkflowStep::findOrFail($id);
            $step->delete();
            $this->updateStepCount($step->workflow_id);
            return response()->json([
                'status' => 'success',
                'message' => 'Workflow step deleted successfully'
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Workflow step not found',
                'error' => $e->getMessage()
            ], 404);
        } catch (QueryException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete workflow step',
                'error' => $e->getMessage()
            ], 500);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unexpected error occurred',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    public function reorder(Request $request)
    {
        $validated = $request->validate([
            'workflow_id' => 'required|integer|exists:work_flows,id',
            'steps' => 'required|array|min:1',
            'steps.*.id' => 'required|integer|exists:workflow_steps,id',
            'steps.*.order_id' => 'required|integer|min:1',
        ]);

        try {
            DB::beginTransaction();

            $workflowId = $validated['workflow_id'];

            foreach ($validated['steps'] as $stepData) {
                $stepId = $stepData['id'];
                $newOrder = $stepData['order_id'];

                // Get current step
                $step = WorkflowStep::where('workflow_id', $workflowId)->find($stepId);
                if (!$step) continue;

                $currentOrder = $step->order_id;

                if ($newOrder == $currentOrder) {
                    continue; // nothing to change
                }

                if ($newOrder < $currentOrder) {
                    // Moving UP — shift down between newOrder and currentOrder - 1
                    WorkflowStep::where('workflow_id', $workflowId)
                        ->whereBetween('order_id', [$newOrder, $currentOrder - 1])
                        ->increment('order_id');
                } else {
                    // Moving DOWN — shift up between currentOrder + 1 and newOrder
                    WorkflowStep::where('workflow_id', $workflowId)
                        ->whereBetween('order_id', [$currentOrder + 1, $newOrder])
                        ->decrement('order_id');
                }

                // Finally, update this step to new order
                $step->update(['order_id' => $newOrder]);
            }

            DB::commit();
            $this->updateStepCount($workflowId);
            return response()->json([
                'status' => 'success',
                'message' => 'Workflow steps reordered successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to reorder steps',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


    public function getStepByWorkflow($id)
    {
        $steps = WorkflowStep::where('workflow_id', $id)->get();

        return response()->json([
            'status' => 'success',
            'steps' => $steps
        ]);
    }
}
