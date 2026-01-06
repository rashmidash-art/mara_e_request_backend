<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WorkflowStep;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class WorkFlowStepsController extends Controller
{
    protected function updateStepCount(int $workflowId)
    {
        $stepCount = WorkflowStep::where('workflow_id', $workflowId)->count();
        DB::table('work_flows')->where('id', $workflowId)->update(['steps' => $stepCount]);
    }

    /**
     * Display a listing of all workflow steps.
     */
    public function index()
    {
        try {
            $steps = WorkflowStep::orderByRaw("CASE WHEN status = 'Yes' THEN 0 ELSE 1 END")
                ->orderBy('order_id', 'asc')
                ->get();

            return response()->json([
                'status' => 'success',
                'message' => 'Workflow steps retrieved successfully',
                'data' => $steps,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve workflow steps',
                'error' => $e->getMessage(),
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
            'entity_id' => 'nullable|integer',
            'order_id' => 'nullable|integer|min:1',
            'name' => 'required|string|max:255',
            'form_type' => 'nullable|string|max:255',
            'sla_hour' => 'nullable|integer',
            'description' => 'nullable|string',
            'escalation' => 'nullable|string|max:255',
            'status' => 'nullable|string', // "Yes" / "No"
        ]);

        DB::beginTransaction();
        try {
            if (($validated['status'] ?? 'No') === 'Yes') {
                $exists = WorkflowStep::where('workflow_id', $validated['workflow_id'])
                    ->where('status', 'Yes')
                    ->exists();

                if ($exists) {
                    throw ValidationException::withMessages([
                        'status' => 'Only one request step is allowed per workflow.',
                    ]);
                }
                WorkflowStep::where('workflow_id', $validated['workflow_id'])
                    ->increment('order_id');

                $validated['order_id'] = 1;
            }
            if (($validated['status'] ?? 'No') !== 'Yes' && empty($validated['order_id'])) {
                $maxOrder = WorkflowStep::where('workflow_id', $validated['workflow_id'])
                    ->max('order_id');
                $validated['order_id'] = $maxOrder ? $maxOrder + 1 : 1;
            }

            $step = WorkflowStep::create($validated);

            $this->updateStepCount($validated['workflow_id']);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Workflow step created successfully',
                'data' => $step,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create workflow step',
                'error' => $e->getMessage(),
            ], 400);
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
                'data' => $step,
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Workflow step not found',
                'error' => $e->getMessage(),
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve workflow step',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update a workflow step.
     */
    public function update(Request $request, $id)
    {
        DB::beginTransaction();
        try {
            $step = WorkflowStep::findOrFail($id);

            $validated = $request->validate([
                'workflow_id' => 'required|integer|exists:work_flows,id',
                'entity_id' => 'nullable|integer',

                'order_id' => [
                    'nullable',
                    'integer',
                    'min:1',
                    Rule::unique('workflow_steps')
                        ->where(fn ($query) => $query->where('workflow_id', $request->workflow_id))
                        ->ignore($id),
                ],

                'name' => 'required|string|max:255',
                'form_type' => 'nullable|string|max:255',
                'sla_hour' => 'nullable|integer',
                'description' => 'nullable|string',
                'escalation' => 'nullable|string|max:255',
                'status' => 'nullable|string|max:10', // Yes / No
            ]);

            $oldStatus = $step->status ?? 'No';
            $newStatus = $validated['status'] ?? 'No';

            if ($newStatus === 'Yes' && $oldStatus !== 'Yes') {
                $exists = WorkflowStep::where('workflow_id', $validated['workflow_id'])
                    ->where('status', 'Yes')
                    ->where('id', '!=', $id)
                    ->exists();

                if ($exists) {
                    throw ValidationException::withMessages([
                        'status' => 'Only one request step is allowed per workflow.',
                    ]);
                }
                WorkflowStep::where('workflow_id', $validated['workflow_id'])
                    ->where('id', '!=', $id)
                    ->increment('order_id');
                $validated['order_id'] = 1;
            }
            if ($oldStatus === 'Yes' && $newStatus !== 'Yes') {
                $step->update(['status' => 'No']); // Temporarily mark No to avoid conflicts
                $steps = WorkflowStep::where('workflow_id', $validated['workflow_id'])
                    ->orderBy('order_id')
                    ->get();
                $order = 1;
                foreach ($steps as $s) {
                    if ($s->id !== $id) {
                        $s->update(['order_id' => $order++]);
                    }
                }
            }
            $step->update($validated);
            $this->updateStepCount($validated['workflow_id']);
            DB::commit();
            return response()->json([
                'status' => 'success',
                'message' => 'Workflow step updated successfully',
                'data' => $step,
            ], 200);

        } catch (ModelNotFoundException $e) {
            DB::rollBack();

            return response()->json([
                'status' => 'error',
                'message' => 'Workflow step not found',
            ], 404);

        } catch (ValidationException $e) {
            DB::rollBack();

            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'status' => 'error',
                'message' => 'Unexpected error occurred',
                'error' => $e->getMessage(),
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
                'message' => 'Workflow step deleted successfully',
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Workflow step not found',
                'error' => $e->getMessage(),
            ], 404);
        } catch (QueryException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete workflow step',
                'error' => $e->getMessage(),
            ], 500);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unexpected error occurred',
                'error' => $e->getMessage(),
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
        DB::beginTransaction();
        try {
            $workflowId = $validated['workflow_id'];

            foreach ($validated['steps'] as $stepData) {
                $step = WorkflowStep::where('workflow_id', $workflowId)
                    ->find($stepData['id']);
                if (! $step) {
                    continue;
                }
                if ($step->status === 'Yes') {
                    if ((int) $stepData['order_id'] !== 1) {
                        throw new \Exception('Request step must remain as the first step');
                    }
                    $step->update(['order_id' => 1]);
                    continue;
                }
                $currentOrder = $step->order_id;
                $newOrder = $stepData['order_id'];
                if ($newOrder == $currentOrder) {
                    continue;
                }
                if ($newOrder < $currentOrder) {
                    WorkflowStep::where('workflow_id', $workflowId)
                        ->whereBetween('order_id', [$newOrder, $currentOrder - 1])
                        ->where('status', '!=', 'Yes') // skip request step
                        ->increment('order_id');
                } else {
                    WorkflowStep::where('workflow_id', $workflowId)
                        ->whereBetween('order_id', [$currentOrder + 1, $newOrder])
                        ->where('status', '!=', 'Yes') // skip request step
                        ->decrement('order_id');
                }
                $step->update(['order_id' => $newOrder]);
            }
            $this->updateStepCount($workflowId);
            DB::commit();
            return response()->json([
                'status' => 'success',
                'message' => 'Workflow steps reordered successfully',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 400);
        }
    }
    public function getStepByWorkflow($id)
    {
        $steps = WorkflowStep::where('workflow_id', $id)->get();

        return response()->json([
            'status' => 'success',
            'steps' => $steps,
        ]);
    }
}
