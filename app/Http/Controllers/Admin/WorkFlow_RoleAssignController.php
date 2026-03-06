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
        try {
            // ------------------------- 1. Validation ------------------------- //
            $rules = [
                'entity_id' => 'required|integer',
                'workflow_id' => 'required|integer',
                'step_id' => 'required|integer',
                'role_id' => 'required|integer',
                'approval_logic' => 'required|string|in:single,or,and', // allowed logics
                'remark' => 'nullable|string',
                'user_id' => 'required|array|min:1', // always require selected users
            ];

            $request->validate($rules);

            $entity_id = $request->input('entity_id');
            $workflow_id = $request->input('workflow_id');
            $step_id = $request->input('step_id');
            $role_id = $request->input('role_id');
            $approval_logic = strtolower($request->input('approval_logic'));
            $remark = $request->input('remark', null);

            $user_ids = $request->input('user_id'); // users selected in the form

            // ------------------------- 2. Handle SINGLE logic ------------------------- //
            if ($approval_logic === 'single') {
                if (count($user_ids) > 1) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Single approval logic can have only one user.',
                    ], 422);
                }
            }

            // ------------------------- 3. Insert workflow role assignments ------------------------- //
            foreach ($user_ids as $user_id) {
                WorkflowRoleAssign::create([
                    'entity_id' => $entity_id,
                    'workflow_id' => $workflow_id,
                    'step_id' => $step_id,
                    'role_id' => $role_id,
                    'approval_logic' => $approval_logic,
                    'specific_user' => 1, // always specific user
                    'user_id' => $user_id,
                    'remark' => $remark,
                ]);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Workflow role assigned successfully.',
            ], 201);

        } catch (\Exception $e) {
            Log::error('WorkflowRoleAssign store error', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to store workflow role assignment.',
                'error' => $e->getMessage(),
            ], 500);
        }
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

            $request->validate([
                'entity_id' => 'required|integer',
                'workflow_id' => 'required|integer',
                'step_id' => 'required|integer',
                'role_id' => 'required|integer',
                'approval_logic' => 'required|string',
                'remark' => 'nullable|string',
            ]);

            $approval_logic = strtolower($request->approval_logic);
            $remark = $request->remark;

            // Normalize users
            $user_ids = is_array($request->user_id)
                ? $request->user_id
                : [$request->user_id];

            /*
            |--------------------------------------------------------------------------
            | SINGLE LOGIC
            |--------------------------------------------------------------------------
            */
            if ($approval_logic === 'single') {

                $existing = WorkflowRoleAssign::where([
                    'entity_id' => $request->entity_id,
                    'workflow_id' => $request->workflow_id,
                    'step_id' => $request->step_id,
                ])->orderBy('id')->get();

                if ($existing->count()) {

                    $first = $existing->first();

                    // Update first record
                    $first->update([
                        'role_id' => $request->role_id,
                        'approval_logic' => 'single',
                        'specific_user' => 1,
                        'user_id' => $user_ids[0],
                        'remark' => $remark,
                    ]);

                    // Remove extra users
                    WorkflowRoleAssign::where([
                        'entity_id' => $request->entity_id,
                        'workflow_id' => $request->workflow_id,
                        'step_id' => $request->step_id,
                    ])
                        ->where('id', '!=', $first->id)
                        ->delete();

                } else {

                    WorkflowRoleAssign::create([
                        'entity_id' => $request->entity_id,
                        'workflow_id' => $request->workflow_id,
                        'step_id' => $request->step_id,
                        'role_id' => $request->role_id,
                        'approval_logic' => 'single',
                        'specific_user' => 1,
                        'user_id' => $user_ids[0],
                        'remark' => $remark,
                    ]);
                }
            }

            /*
            |--------------------------------------------------------------------------
            | AND / OR LOGIC
            |--------------------------------------------------------------------------
            */
            else {

                // Delete users not selected anymore
                WorkflowRoleAssign::where([
                    'entity_id' => $request->entity_id,
                    'workflow_id' => $request->workflow_id,
                    'step_id' => $request->step_id,
                ])
                    ->whereNotIn('user_id', $user_ids)
                    ->delete();

                foreach ($user_ids as $user_id) {

                    WorkflowRoleAssign::updateOrCreate(
                        [
                            'entity_id' => $request->entity_id,
                            'workflow_id' => $request->workflow_id,
                            'step_id' => $request->step_id,
                            'user_id' => $user_id,
                        ],
                        [
                            'role_id' => $request->role_id,
                            'approval_logic' => $approval_logic,
                            'specific_user' => 1,
                            'remark' => $remark,
                        ]
                    );
                }
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Workflow role assignment updated successfully.',
            ], 200);

        } catch (\Exception $e) {

            Log::error('WorkflowRoleAssign update error', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update workflow role assignment.',
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
