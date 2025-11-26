<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Request as ModelsRequest;
use App\Models\RequestWorkflowDetails;
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


    public function takeAction(Request $request)
    {
        $validated = $request->validate([
            'request_id' => 'required|string',
            'step_id' => 'required|integer',
            'action' => 'required|in:approve,reject,sendback',
            'remark' => 'nullable|string',
        ]);

        $user = Auth::user();
        $roleIds = $user->roles->pluck('id');

        /*  Validate user belongs to this workflow step */
        $current = RequestWorkflowDetails::where('request_id', $request->request_id)
            ->where('workflow_step_id', $request->step_id)
            ->whereIn('workflow_role_id', $roleIds)
            ->where('status', 'pending')
            ->first();

        if (!$current) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized action'
            ], 403);
        }


        /*  SEQUENTIAL CHECK — cannot act until all previous steps are approved */
        $previousPending = RequestWorkflowDetails::where('request_id', $request->request_id)
            ->where('workflow_step_id', '<', $current->workflow_step_id)
            ->where('status', '!=', 'approved')
            ->count();

        if ($previousPending > 0) {
            return response()->json([
                'status' => 'error',
                'message' => 'You cannot take action until previous approvers complete their steps.',
            ], 403);
        }


        /* --------------------------
         APPROVE ACTION
       -------------------------- */
        if ($request->action === 'approve') {

            $current->update([
                'status' => 'approved',
                'remark' => $request->remark,
                'action_taken_by' => $user->id,
            ]);

            // Get next step in workflow
            $nextStep = WorkflowStep::where('workflow_id', $current->workflow_id)
                ->where('order_id', '>', $current->workflowStep->order_id)
                ->orderBy('order_id')
                ->first();

            if ($nextStep) {
                // Activate next step
                RequestWorkflowDetails::where('request_id', $request->request_id)
                    ->where('workflow_step_id', $nextStep->id)
                    ->update(['status' => 'pending']);
            } else {
                // Final approval
                ModelsRequest::where('request_id', $request->request_id)
                    ->update(['status' => 'approved']);
            }

            return ['status' => 'success', 'message' => 'Approved'];
        }


        /* --------------------------
         REJECT ACTION
       -------------------------- */
        if ($request->action === 'reject') {

            $current->update([
                'status' => 'rejected',
                'remark' => $request->remark,
                'action_taken_by' => $user->id
            ]);

            ModelsRequest::where('request_id', $request->request_id)
                ->update(['status' => 'rejected']);

            return ['status' => 'success', 'message' => 'Rejected'];
        }


        /* --------------------------
         SEND BACK ACTION
       -------------------------- */
        if ($request->action === 'sendback') {

            $current->update([
                'status' => 'sendback',
                'remark' => $request->remark,
                'action_taken_by' => $user->id,
            ]);

            ModelsRequest::where('request_id', $request->request_id)
                ->update(['status' => 'sendback']);

            return ['status' => 'success', 'message' => 'Sent Back'];
        }
    }
}
