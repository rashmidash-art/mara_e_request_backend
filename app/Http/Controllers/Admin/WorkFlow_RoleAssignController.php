<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\WorkflowRoleAssign;
use App\Models\WorkflowUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WorkFlow_RoleAssignController extends Controller
{
    public function index()
    {
        try {
            $assignments = WorkflowRoleAssign::with([
                'role:id,name,display_name',
                'workflow:id,name',
                'step:id,name',
            ])->get();

            // Group assignments by step_id, role_id, workflow_id
            $groupedAssignments = [];

            foreach ($assignments as $assignment) {
                $key = "{$assignment->workflow_id}_{$assignment->step_id}_{$assignment->role_id}";

                if (! isset($groupedAssignments[$key])) {
                    $groupedAssignments[$key] = [
                        'id' => $assignment->id,
                        'entity_id' => $assignment->entity_id,
                        'workflow_id' => $assignment->workflow_id,
                        'step_id' => $assignment->step_id,
                        'role_id' => $assignment->role_id,
                        'approval_logic' => $assignment->approval_logic,
                        'remark' => $assignment->remark,
                        'role' => $assignment->role,
                        'workflow' => $assignment->workflow,
                        'step' => $assignment->step,
                        'user_id' => [],
                        'assigned_users' => [],
                    ];
                }

                // Add user_id to array
                $groupedAssignments[$key]['user_id'][] = $assignment->user_id;

                // Get user details
                $user = User::find($assignment->user_id);
                if ($user) {
                    $groupedAssignments[$key]['assigned_users'][] = [
                        'id' => $user->id,
                        'name' => $user->name,
                    ];
                }
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Workflow role assignments retrieved successfully.',
                'data' => array_values($groupedAssignments),
            ], 200);
        } catch (\Exception $e) {
            Log::error('WorkflowRoleAssign index error', ['error' => $e->getMessage()]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve workflow role assignments.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Store workflow role assignment(s)
     */
    public function store(Request $request)
    {
        // Step 1: Conditional validation
        $rules = [
            'entity_id' => 'required|integer',
            'workflow_id' => 'required|integer',
            'step_id' => 'required|integer',
            'role_id' => 'required|integer',
            'approval_logic' => 'required|string', // 'single', 'or', 'and'
            'remark' => 'nullable|string',
        ];

        if ($request->input('approval_logic') === 'or') {
            $rules['user_id'] = 'required|array|min:1';
        }

        $request->validate($rules);

        // Step 2: Get inputs
        $entity_id = $request->input('entity_id');
        $workflow_id = $request->input('workflow_id');
        $step_id = $request->input('step_id');
        $role_id = $request->input('role_id');
        $approval_logic = $request->input('approval_logic');
        $remark = $request->input('remark', null);

        $user_ids = $request->input('user_id', []); // Will be empty if not provided

        // Step 3: Fetch users for entity and role if needed
        if ($approval_logic === 'single' || $approval_logic === 'and') {
            // Fetch all users from pivot table for this entity and role
            $user_ids = DB::table('role_user') // Assuming pivot table is role_user
                ->where('role_id', $role_id)
                ->pluck('user_id')
                ->toArray();
        }

        // Step 4: Ensure $user_ids is always an array
        if (! is_array($user_ids)) {
            $user_ids = [$user_ids];
        }

        // Step 5: Insert workflow role assignments
        foreach ($user_ids as $user_id) {
            DB::table('workflow_role_assigns')->insert([
                'entity_id' => $entity_id,
                'workflow_id' => $workflow_id,
                'step_id' => $step_id,
                'role_id' => $role_id,
                'approval_logic' => $approval_logic,
                'specific_user' => 1, // Assuming 1 means specific user
                'user_id' => $user_id,
                'remark' => $remark,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Step 6: Return response
        return response()->json([
            'status' => 'success',
            'message' => 'Workflow role assigned successfully.',
        ]);
    }

    public function show(int $id)
    {
        try {
            $assignment = WorkflowUser::with(['user:id,name', 'role:id,name', 'workflow:id,name', 'step:id,name'])
                ->findOrFail($id);

            return response()->json([
                'status' => 'success',
                'message' => 'Workflow role assignment retrieved successfully.',
                'data' => $assignment,
            ], 200);

        } catch (\Exception $e) {
            Log::error('WorkflowUser show error', ['error' => $e->getMessage()]);

            return response()->json([
                'status' => 'error',
                'message' => 'Workflow role assignment not found.',
                'error' => $e->getMessage(),
            ], 404);
        }
    }

    public function update(Request $request, int $id)
    {
        try {
            $assignment = WorkflowRoleAssign::findOrFail($id);

            // Step 1: Validation
            $rules = [
                'entity_id' => 'required|integer',
                'workflow_id' => 'required|integer',
                'step_id' => 'required|integer',
                'role_id' => 'required|integer',
                'approval_logic' => 'required|string', // 'Single', 'OR', 'AND'
                'remark' => 'nullable|string',
            ];

            // Only validate user_id as array for OR logic
            if (strtolower($request->input('approval_logic')) === 'or') {
                $rules['user_id'] = 'required|array|min:1';
            }

            $request->validate($rules);

            // Step 2: Get inputs
            $entity_id = $request->input('entity_id');
            $workflow_id = $request->input('workflow_id');
            $step_id = $request->input('step_id');
            $role_id = $request->input('role_id');
            $approval_logic = $request->input('approval_logic'); // Keep original case
            $remark = $request->input('remark', null);

            $user_ids = $request->input('user_id', []); // Will be empty if not provided

            // Step 3: Fetch users for entity and role if needed (for Single and AND)
            if (strtolower($approval_logic) === 'single' || strtolower($approval_logic) === 'and') {
                // Fetch all users from pivot table for this role
                $user_ids = DB::table('role_user')
                    ->where('role_id', $role_id)
                    ->pluck('user_id')
                    ->toArray();
            }

            // Step 4: Ensure $user_ids is always an array
            if (! is_array($user_ids)) {
                $user_ids = [$user_ids];
            }

            // Step 5: Delete existing assignments for this step & role
            WorkflowRoleAssign::where('workflow_id', $workflow_id)
                ->where('step_id', $step_id)
                ->where('role_id', $role_id)
                ->delete();

            // Step 6: Insert updated assignments
            foreach ($user_ids as $user_id) {
                DB::table('workflow_role_assigns')->insert([
                    'entity_id' => $entity_id,
                    'workflow_id' => $workflow_id,
                    'step_id' => $step_id,
                    'role_id' => $role_id,
                    'approval_logic' => $approval_logic,
                    'specific_user' => 1, // Same as store method
                    'user_id' => $user_id,
                    'remark' => $remark,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Workflow role assignment updated successfully.',
            ], 200);

        } catch (\Exception $e) {
            Log::error('WorkflowRoleAssign update error', ['error' => $e->getMessage()]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update workflow role assignment.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete workflow role assignment(s)
     */
    public function destroy(int $id)
    {
        try {
            $assignment = WorkflowRoleAssign::findOrFail($id);

            // Delete all assignments for the same step & role in the workflow
            WorkflowRoleAssign::where('workflow_id', $assignment->workflow_id)
                ->where('step_id', $assignment->step_id)
                ->where('role_id', $assignment->role_id)
                ->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Workflow role assignment(s) deleted successfully.',
            ], 200);

        } catch (\Exception $e) {
            Log::error('WorkflowRoleAssign delete error', ['error' => $e->getMessage()]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete workflow role assignment.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get roles assigned to a workflow step.
     */
    public function getRolesByStep(int $workflow_id, int $step_id)
    {
        $roles = DB::table('workflow_users as wu')
            ->join('roles as r', 'r.id', '=', 'wu.role_id')
            ->where('wu.workflow_id', $workflow_id)
            ->where('wu.step_id', $step_id)
            ->select('r.id', 'r.name')
            ->distinct()
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $roles,
        ]);
    }

    /**
     * Get roles by workflow and step from workflow_role_assigns table
     */
    public function getRolesByWorkflowStep($workflow_id, $step_id)
    {
        $roles = DB::table('workflow_role_assigns as wsr')
            ->join('roles as r', 'r.id', '=', 'wsr.role_id')
            ->where('wsr.workflow_id', $workflow_id)
            ->where('wsr.step_id', $step_id)
            ->select('r.id', 'r.name')
            ->distinct()
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $roles,
        ]);
    }

    public function getAssignedSteps($workflow_id)
    {
        // Only steps that have assignments AND escalation enabled
        $steps = DB::table('workflow_steps as ws')
            ->join('workflow_role_assigns as wra', function ($join) use ($workflow_id) {
                $join->on('ws.id', '=', 'wra.step_id')
                    ->where('wra.workflow_id', $workflow_id);
            })
            ->where('ws.escalation', 'enable')  // Only steps with escalation enabled
            ->select('ws.id', 'ws.name', 'ws.order_id')
             ->select('ws.id', 'ws.name', 'ws.order_id', 'ws.sla_hour')
            ->distinct()
            ->orderBy('ws.order_id', 'ASC')
            ->get();

        return response()->json([
            'status' => 'success',
            'steps' => $steps,
        ]);
    }

    public function getUnassignedSteps($workflow_id)
    {
        // Get step IDs that already have role assignments for this workflow
        $assignedStepIds = DB::table('workflow_role_assigns')
            ->where('workflow_id', $workflow_id)
            ->distinct()
            ->pluck('step_id');

        // Get unassigned steps with escalation enabled
        $unassignedSteps = DB::table('workflow_steps as ws')
            ->where('ws.workflow_id', $workflow_id)
            ->where('ws.escalation', 'enable')  // Only steps with escalation enabled
            ->whereNotIn('ws.id', $assignedStepIds)  // Steps not yet assigned
            ->select('ws.id', 'ws.name', 'ws.order_id')
            ->orderBy('ws.order_id', 'ASC')
            ->get();

        return response()->json([
            'status' => 'success',
            'steps' => $unassignedSteps,
        ]);
    }
}
