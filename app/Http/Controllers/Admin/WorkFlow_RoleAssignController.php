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
            $assignments = WorkflowRoleAssign::with(['role:id,name', 'workflow:id,name', 'step:id,name'])->get();

            $assignments->transform(function ($assignment) {
                $users = $assignment->assignedUsers();
                $assignment->assigned_users = $users->pluck('name')->toArray();

                return $assignment;
            });

            return response()->json([
                'status' => 'success',
                'message' => 'Workflow role assignments retrieved successfully.',
                'data' => $assignments,
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
        $request->validate([
            'entity_id' => 'required|integer',
            'workflow_id' => 'required|integer',
            'step_id' => 'required|integer',
            'role_id' => 'required|integer',
            'approval_logic' => 'required|string',
            'user_id' => 'required', // Can be single or array
            'remark' => 'nullable|string',
        ]);

        $entity_id = $request->input('entity_id');
        $workflow_id = $request->input('workflow_id');
        $step_id = $request->input('step_id');
        $role_id = $request->input('role_id');
        $approval_logic = $request->input('approval_logic');
        $remark = $request->input('remark', null);

        $user_ids = $request->input('user_id');

        // Ensure $user_ids is always an array
        if (! is_array($user_ids)) {
            $user_ids = [$user_ids];
        }

        // Loop through each user and insert a record
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

        return response()->json([
            'status' => 'success',
            'message' => 'Workflow role assigned successfully.',
        ]);
    }

    /**
     * Update workflow role assignment
     */
    public function update(Request $request, int $id)
    {
        try {
            $assignment = WorkflowRoleAssign::findOrFail($id);

            $entity_id = $request->input('entity_id', $assignment->entity_id);
            $workflow_id = $request->input('workflow_id', $assignment->workflow_id);
            $step_id = $request->input('step_id', $assignment->step_id);
            $role_id = $request->input('role_id', $assignment->role_id);
            $approval_logic = strtolower($request->input('approval_logic', $assignment->approval_logic));
            $user_ids = array_map('intval', (array) $request->input('user_id')); // ensure array
            $remark = $request->input('remark', $assignment->remark);
            $specific_user = ($approval_logic === 'or') ? 1 : 0;

            // Update workflow_role_assigns
            $assignment->update([
                'entity_id' => $entity_id,
                'workflow_id' => $workflow_id,
                'step_id' => $step_id,
                'role_id' => $role_id,
                'approval_logic' => ucfirst($approval_logic),
                'specific_user' => $specific_user,
                'user_id' => ($approval_logic === 'or') ? json_encode($user_ids) : null,
                'remark' => $remark,
            ]);

            // Delete old WorkflowUser entries for this step & role
            WorkflowUser::where('workflow_id', $workflow_id)
                ->where('step_id', $step_id)
                ->where('role_id', $role_id)
                ->delete();

            // Determine users to assign
            $usersToAssign = collect();

            if ($approval_logic === 'single' || $approval_logic === 'and') {
                $usersToAssign = User::whereHas('roles', function ($q) use ($role_id) {
                    $q->where('role_id', $role_id);
                })->get();
            } elseif ($approval_logic === 'or' && ! empty($user_ids)) {
                $usersToAssign = User::whereIn('id', $user_ids)->get();
            }

            // Insert new WorkflowUser entries
            foreach ($usersToAssign as $user) {
                WorkflowUser::updateOrCreate(
                    [
                        'workflow_id' => $workflow_id,
                        'step_id' => $step_id,
                        'role_id' => $role_id,
                        'user_id' => $user->id,
                    ],
                    [
                        'entity_id' => $entity_id,
                        'logic' => ucfirst($approval_logic),
                        'status' => 'active',
                    ]
                );
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Workflow role assignment updated successfully.',
                'data' => $assignment,
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

    // public function update(Request $request, int $id)
    // {
    //     try {
    //         $assignment = WorkflowUser::findOrFail($id);

    //         $assignment->update([
    //             'entity_id' => $request->input('entity_id', $assignment->entity_id),
    //             'workflow_id' => $request->input('workflow_id', $assignment->workflow_id),
    //             'step_id' => $request->input('step_id', $assignment->step_id),
    //             'role_id' => $request->input('role_id', $assignment->role_id),
    //             'logic' => $request->input('logic', $assignment->logic),
    //             'user_id' => $request->input('user_id', $assignment->user_id),
    //             'status' => $request->input('status', $assignment->status),
    //         ]);

    //         return response()->json([
    //             'status' => 'success',
    //             'message' => 'Workflow role assignment updated successfully.',
    //             'data' => $assignment,
    //         ], 200);

    //     } catch (\Exception $e) {
    //         Log::error('WorkflowUser update error', ['error' => $e->getMessage()]);

    //         return response()->json([
    //             'status' => 'error',
    //             'message' => 'Failed to update workflow role assignment.',
    //             'error' => $e->getMessage(),
    //         ], 500);
    //     }
    // }

    public function destroy(int $id)
    {
        try {
            $assignment = WorkflowUser::findOrFail($id);
            $assignment->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Workflow role assignment deleted successfully.',
            ], 200);

        } catch (\Exception $e) {
            Log::error('WorkflowUser delete error', ['error' => $e->getMessage()]);

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
}
