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
        // Use $request_id here instead of $request->request_id
        $validated = $request->validate([
            'step_id' => 'required|integer',
            'action' => 'required|in:approve,reject,sendback',
            'remark' => 'nullable|string',
        ]);

        // Proceed with the logic using $request_id
        $user = Auth::user();
        $roleIds = $user->roles->pluck('id');

        /*  Validate user belongs to this workflow step */
        $current = RequestWorkflowDetails::where('request_id', $request_id)
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

        // Remaining logic for approve/reject/sendback...
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
