<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\WorkflowRoleAssign;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WorkFlow_RoleAssignController extends Controller
{
    /**
     * Display all role assignments.
     */
    public function index()
    {
        try {
            $assignments = WorkflowRoleAssign::orderByDesc('id')->get();

            return response()->json([
                'status' => 'success',
                'message' => 'Workflow role assignments retrieved successfully.',
                'data' => $assignments,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve workflow role assignments.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function store(Request $request)
    {
        // 🔹 Log incoming request (safe fields only)
        Log::info('WorkflowRoleAssign STORE called', [
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'headers' => $request->headers->all(),
            'payload' => $request->all(),
        ]);

        try {
            $validated = $request->validate([
                'workflow_id' => 'required|integer|exists:work_flows,id',
                'step_id' => 'required|integer|exists:workflow_steps,id',
                'role_id' => 'required|integer|exists:roles,id',
                'approval_logic' => 'required|string|in:single,and,or',
                'specific_user' => 'nullable|integer|in:0,1',
                'user_id' => 'nullable|integer|exists:users,id',
                'remark' => 'nullable|string',
            ]);

            Log::info('WorkflowRoleAssign validation passed', $validated);

            $eligibleUsers = collect();

            if ($validated['approval_logic'] === 'single') {
                if (($validated['specific_user'] ?? 1) == 0 && ! empty($validated['user_id'])) {
                    $eligibleUsers = User::where('id', $validated['user_id'])->get();
                } else {
                    $eligibleUsers = User::where('role_id', $validated['role_id'])->get();
                }
            } elseif (in_array($validated['approval_logic'], ['or', 'and'])) {
                $eligibleUsers = User::where('role_id', $validated['role_id'])->get();
            }

            $assignment = WorkflowRoleAssign::create($validated);

            Log::info('WorkflowRoleAssign created successfully', [
                'assignment_id' => $assignment->id,
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Workflow role assignment created successfully.',
                'data' => [
                    'assignment' => $assignment,
                    'users' => $eligibleUsers,
                ],
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {

            Log::warning('WorkflowRoleAssign validation failed', [
                'errors' => $e->errors(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);

        } catch (QueryException $e) {

            Log::error('WorkflowRoleAssign DB error', [
                'error' => $e->getMessage(),
                'sql' => $e->getSql(),
                'bindings' => $e->getBindings(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Database error',
            ], 500);

        } catch (\Throwable $e) {

            Log::critical('WorkflowRoleAssign unknown error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Server error',
            ], 500);
        }
    }

    /**
     * Update workflow role assignment.
     */
    public function update(Request $request, string $id)
    {
        try {

            $assignment = WorkflowRoleAssign::findOrFail($id);

            $assignment->update([
                'workflow_id' => $request->workflow_id ?? $assignment->workflow_id,
                'step_id' => $request->step_id ?? $assignment->step_id,
                'role_id' => $request->role_id ?? $assignment->role_id,
                'approval_logic' => $request->approval_logic ?? $assignment->approval_logic,
                'specific_user' => $request->specific_user ?? $assignment->specific_user,
                'user_id' => $request->user_id ?? $assignment->user_id,
                'remark' => $request->remark ?? $assignment->remark,
            ]);

            // Recalculate eligible users
            $eligibleUsers = collect();

            if ($assignment->approval_logic === 'single') {
                if ($assignment->specific_user == 0 && ! empty($assignment->user_id)) {
                    $eligibleUsers = User::where('id', $assignment->user_id)->get();
                } else {
                    $eligibleUsers = User::where('role_id', $assignment->role_id)->get();
                }
            } elseif ($assignment->approval_logic === 'or') {
                $eligibleUsers = User::where('role_id', $assignment->role_id)->get();
            } elseif ($assignment->approval_logic === 'and') {
                $eligibleUsers = User::where('role_id', $assignment->role_id)->get();
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Workflow role assignment updated successfully.',
                'data' => [
                    'assignment' => $assignment,
                    'users' => $eligibleUsers,
                ],
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Workflow role assignment not found.',
                'error' => $e->getMessage(),
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update workflow role assignment.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function destroy(string $id)
    {
        try {
            $assignment = WorkflowRoleAssign::findOrFail($id);
            $assignment->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Workflow role assignment deleted successfully.',
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Workflow role assignment not found.',
                'error' => $e->getMessage(),
            ], 404);
        } catch (QueryException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete workflow role assignment.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
