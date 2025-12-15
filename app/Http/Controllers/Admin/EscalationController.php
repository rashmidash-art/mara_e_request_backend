<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Escalations;
use App\Models\WorkflowRoleAssign;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;

class EscalationController extends Controller
{
    public function index()
    {
        try {
            $data = Escalations::with(['workflow', 'step', 'role', 'assignedUser'])->orderByDesc('id')->get();

            return response()->json(['status' => 'success', 'data' => $data]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'workflow_id' => 'nullable|integer|exists:work_flows,id',
            'step_id' => 'nullable|integer|exists:workflow_steps,id',
            'role_id' => 'nullable|integer|exists:roles,id',
            'user_id' => 'nullable|integer', // will check in workflow_role_assigns
            'description' => 'nullable|string',
            'enable_rule' => 'nullable|integer|in:0,1',
            'enable_notification' => 'nullable|integer|in:0,1',
            'enable_mail' => 'nullable|integer|in:0,1',
            'notify_type' => 'nullable|integer|in:0,1,2',
            'sla_hour' => 'nullable|string',
            'escalation_hour' => 'nullable|string',
            'status' => 'nullable|string|in:Active,Inactive',
        ]);

        try {
            //  Only allow user_id if it exists for role_id in workflow_role_assigns
            if (! empty($validated['user_id'])) {
                $exists = WorkflowRoleAssign::where('role_id', $validated['role_id'])
                    ->where('user_id', $validated['user_id'])
                    ->exists();
                if (! $exists) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'User not assigned to this role in workflow_role_assigns',
                    ], 400);
                }
            }

            $escalation = Escalations::create($validated);

            return response()->json([
                'status' => 'success',
                'message' => 'Escalation created successfully',
                'data' => $escalation,
            ]);

        } catch (QueryException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function show(string $id)
    {
        try {
            $data = Escalations::with(['workflow', 'step', 'role', 'assignedUser'])->findOrFail($id);

            return response()->json(['status' => 'success', 'data' => $data]);
        } catch (ModelNotFoundException $e) {
            return response()->json(['status' => 'error', 'message' => 'Escalation not found'], 404);
        }
    }

    public function update(Request $request, string $id)
    {
        try {
            $escalation = Escalations::findOrFail($id);

            $validated = $request->validate([
                'workflow_id' => 'sometimes|integer|exists:work_flows,id',
                'step_id' => 'sometimes|integer|exists:workflow_steps,id',
                'role_id' => 'sometimes|integer|exists:roles,id',
                'user_id' => 'nullable|integer',
                'description' => 'nullable|string',
                'enable_rule' => 'sometimes|integer|in:0,1',
                'enable_notification' => 'sometimes|integer|in:0,1',
                'enable_mail' => 'sometimes|integer|in:0,1',
                'notify_type' => 'sometimes|integer|in:0,1,2',
                'sla_hour' => 'nullable|string',
                'escalation_hour' => 'nullable|string',
                'status' => 'sometimes|string|in:Active,Inactive',
            ]);

            //  Only allow user_id if exists in workflow_role_assigns
            if (! empty($validated['user_id'])) {
                $roleId = $validated['role_id'] ?? $escalation->role_id;
                $exists = WorkflowRoleAssign::where('role_id', $roleId)
                    ->where('user_id', $validated['user_id'])
                    ->exists();
                if (! $exists) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'User not assigned to this role in workflow_role_assigns',
                    ], 400);
                }
            }

            $escalation->update($validated);

            return response()->json([
                'status' => 'success',
                'message' => 'Escalation updated successfully',
                'data' => $escalation,
            ]);

        } catch (ModelNotFoundException $e) {
            return response()->json(['status' => 'error', 'message' => 'Escalation not found'], 404);
        }
    }

    public function destroy(string $id)
    {
        try {
            $escalation = Escalations::findOrFail($id);
            $escalation->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Escalation deleted successfully',
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json(['status' => 'error', 'message' => 'Escalation not found'], 404);
        }
    }
}
