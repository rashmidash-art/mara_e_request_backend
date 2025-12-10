<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Request as ModelsRequest;
use App\Models\RequestWorkflowDetails;
use App\Models\User;
use App\Models\WorkflowRoleAssign;
use App\Models\WorkflowStep;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RequestWorkflowDetailsController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }


    public function takeAction(Request $request, $request_id)
    {
        $validated = $request->validate([
            'action' => 'required|in:approve,reject,sendback',
            'remark' => 'nullable|string',
        ]);

        $user = Auth::user();

        // Get the current pending workflow step for this user
        $current = RequestWorkflowDetails::where('request_id', $request_id)
            ->where('assigned_user_id', $user->id)
            ->where('status', 'pending')
            ->first();

        if (!$current) {
            return response()->json([
                'status' => 'error',
                'message' => 'No pending workflow found for you'
            ], 403);
        }

        // ------------------ APPROVE ------------------ //
        if ($request->action === 'approve') {
            $current->update([
                'status' => 'approved',
                'remark' => $request->remark,
                'is_sendback' => 0,
                'sendback_remark' => null,
                'action_taken_by' => $user->id,
            ]);

            // Move to next step if exists
            $nextStep = RequestWorkflowDetails::where('request_id', $request_id)
                ->where('workflow_step_id', '>', $current->workflow_step_id)
                ->orderBy('workflow_step_id')
                ->first();

            if ($nextStep) {
                $nextStep->update([
                    'status' => 'pending',
                ]);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Request approved successfully'
            ]);
        }

        // ------------------ REJECT ------------------ //
        if ($request->action === 'reject') {
            $current->update([
                'status' => 'rejected',
                'remark' => $request->remark,
                'is_sendback' => 0,
                'sendback_remark' => null,
                'action_taken_by' => $user->id,
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Request rejected successfully'
            ]);
        }

        // ------------------ SENDBACK ------------------ //
        if ($request->action === 'sendback') {
            $current->update([
                'is_sendback' => 1,
                'sendback_remark' => $request->remark,
                'remark' => null,
                'action_taken_by' => $user->id,
            ]);

            // Move to previous step if exists
            $previousStep = RequestWorkflowDetails::where('request_id', $request_id)
                ->where('workflow_step_id', '<', $current->workflow_step_id)
                ->orderByDesc('workflow_step_id')
                ->first();

            if ($previousStep) {
                $previousStep->update([
                    'status' => 'pending',
                ]);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Request sent back successfully'
            ]);
        }
    }





    private function getEligibleUsers(WorkflowRoleAssign $assign)
    {
        // Single logic with user selected
        if (
            $assign->approval_logic === 'single' &&
            $assign->specific_user == 0 &&
            $assign->user_id
        ) {
            return User::where('id', $assign->user_id)->get();
        }

        return User::where('role_id', $assign->role_id)->get();
    }
}
