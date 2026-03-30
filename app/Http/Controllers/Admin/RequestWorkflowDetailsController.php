<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Request as ModelsRequest; // Eloquent model alias
use App\Models\RequestWorkflowDetails;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RequestWorkflowDetailsController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $userId = Auth::id();

        $requests = ModelsRequest::whereHas('workflowUsers', function ($q) use ($userId) {
            $q->where('action_taken_by', $userId);
        })
            ->with([
                'userData',
                'departmentData',
                'workflowUsers' => function ($q) use ($userId) {
                    $q->where('action_taken_by', $userId)
                        ->latest('updated_at')
                        ->limit(1)
                        ->with(['assignedUser', 'role', 'workflowStep']);
                },
                'requestDetailsDocuments',
                'supplierData',
                'requestTypeData',
                'categoryData',
            ])
            ->orderBy('updated_at', 'desc')
            ->get();

        $data = $requests->map(function ($request) {
            $lastActionByUser = $request->workflowUsers->first();

            return [
                'id' => $request->request_id,
                'title' => $request->title,
                'description' => $request->description,
                'amount' => $request->amount,
                'type' => $request->requestTypeData->name ?? null,
                'priority' => $request->priority ?? null,
                'requestor' => [
                    'id' => $request->userData->id ?? null,
                    'name' => $request->userData->name ?? null,
                    'email' => $request->userData->email ?? null,
                    'department' => $request->departmentData->name ?? null,
                ],
                'status' => $lastActionByUser->status ?? 'pending',
                'last_action_by_user' => $lastActionByUser ? [
                    'workflow_id' => $lastActionByUser->workflow_id,
                    'workflow_step_id' => $lastActionByUser->workflow_step_id,
                    'workflow_role_id' => $lastActionByUser->workflow_role_id,
                    'assigned_user_id' => $lastActionByUser->assigned_user_id,
                    'action_taken_by' => $lastActionByUser->action_taken_by,
                    'remark' => $lastActionByUser->remark,
                    'status' => $lastActionByUser->status,
                    'is_sendback' => $lastActionByUser->is_sendback,
                    'sendback_remark' => $lastActionByUser->sendback_remark,
                    'created_at' => $lastActionByUser->created_at,
                    'updated_at' => $lastActionByUser->updated_at,
                ] : null,
                'is_closed' => $request->status === 'closed',
                'category' => $request->categoryData->name ?? null,
                'workflow' => $lastActionByUser?->workflowStep->name ?? null,
                'supplier_name' => $request->supplierData->name ?? null,
                'created_at' => $request->created_at,
                'updated_at' => $request->updated_at,
            ];
        });

        $counts = [
            'total' => $data->count(),
            'approve' => $data->where('status', 'approve')->count(),
            // 'approve' => $data->whereIn('status', [
            //     'approve',
            //     'approved',
            //     'po_created',
            //     'payment_completed',
            //     'delivery_completed',
            // ])->count(),
            'rejected' => $data->where('status', 'rejected')->count(),
            'pending' => $data->where('status', 'pending')->count(),
        ];

        return response()->json([
            'status' => 'success',
            'data' => $data,
            'counts' => $counts,
        ]);
    }

    /**
     * Take workflow action (approve/reject/sendback)
     */

    // public function takeAction(Request $request, $request_id)
    // {
    //     $validated = $request->validate([
    //         'action' => 'required|in:approve,reject,sendback',
    //         'remark' => 'nullable|string',
    //     ]);

    //     $user = Auth::user();
    //     if (! $user) {
    //         return response()->json(['status' => 'error', 'message' => 'Unauthenticated'], 401);
    //     }

    //     $current = RequestWorkflowDetails::where('request_id', $request_id)
    //         ->where('assigned_user_id', $user->id)
    //         ->where('status', 'pending')
    //         ->first();

    //     if (! $current) {
    //         return response()->json(['status' => 'error', 'message' => 'No pending workflow for you'], 403);
    //     }

    //     $logic = strtolower($current->approval_logic); // single / or / and
    //     $requestData = ModelsRequest::where('request_id', $request_id)->first();

    //     if (! $requestData) {
    //         return response()->json(['status' => 'error', 'message' => 'Request not found'], 404);
    //     }

    //     // ------------------ APPROVE ------------------ //
    //     if ($request->action === 'approve') {

    //         if ($requestData->amount > $user->loa) {
    //             return response()->json([
    //                 'status' => 'error',
    //                 'message' => 'You cannot approve: LOA too low',
    //             ], 403);
    //         }

    //         if ($logic === 'single' || $logic === 'and') {
    //             $current->update([
    //                 'status' => 'approved',
    //                 'remark' => $request->remark,
    //                 'action_taken_by' => $user->id,
    //             ]);

    //             // For AND logic, notify requestor only after all assigned users approve
    //             if ($logic === 'and') {
    //                 $pendingApprovals = RequestWorkflowDetails::where('request_id', $request_id)
    //                     ->where('workflow_step_id', $current->workflow_step_id)
    //                     ->where('status', 'pending')
    //                     ->count();

    //                 if ($pendingApprovals === 0) {
    //                     NotificationService::send(
    //                         $requestData->user,
    //                         'Request Approved',
    //                         "Your request {$requestData->request_id} has been approved by all assigned approvers.",
    //                         'request_approved',
    //                         $requestData->request_id,
    //                         'request',
    //                         'Workflow approval'
    //                     );
    //                 }
    //             } else {
    //                 // single logic
    //                 NotificationService::send(
    //                     $requestData->user,
    //                     'Request Approved',
    //                     "Your request {$requestData->request_id} has been approved by {$user->name}.",
    //                     'request_approved',
    //                     $requestData->request_id,
    //                     'request',
    //                     'Workflow approval'
    //                 );
    //             }

    //         } elseif ($logic === 'or') {
    //             // OR logic: notify requestor immediately
    //             RequestWorkflowDetails::where('request_id', $request_id)
    //                 ->where('workflow_step_id', $current->workflow_step_id)
    //                 ->update([
    //                     'status' => 'approved',
    //                     'remark' => $request->remark,
    //                     'action_taken_by' => $user->id,
    //                 ]);

    //             NotificationService::send(
    //                 $requestData->user,
    //                 'Request Approved',
    //                 "Your request {$requestData->request_id} has been approved by {$user->name}.",
    //                 'request_approved',
    //                 $requestData->request_id,
    //                 'request',
    //                 'Workflow approval'
    //             );
    //         }

    //         $this->syncRequestStatus($request_id);

    //         return response()->json(['status' => 'success', 'message' => 'Approved successfully']);
    //     }
    //     // ------------------ REJECT ------------------ //
    //     if ($request->action === 'reject') {
    //         RequestWorkflowDetails::where('request_id', $request_id)
    //             ->where('workflow_step_id', $current->workflow_step_id)
    //             ->update([
    //                 'status' => 'rejected',
    //                 'remark' => $request->remark,
    //                 'action_taken_by' => $user->id,
    //                 'is_sendback' => 0,
    //                 'sendback_remark' => null,
    //             ]);

    //         $this->syncRequestStatus($request_id);
    //         // --- NOTIFY REQUESTOR ---
    //         NotificationService::send(
    //             $requestData->user,
    //             'Request Rejected',
    //             "Your request {$requestData->request_id} has been rejected by {$user->name}.",
    //             'request_rejected',
    //             $requestData->request_id,
    //             'request',
    //             'Workflow rejection'
    //         );

    //         return response()->json(['status' => 'success', 'message' => 'Rejected successfully']);
    //     }

    //     // ------------------ SENDBACK ------------------ //

    //     // if ($request->action === 'sendback') {

    //     //     $current->update([
    //     //         'is_sendback' => 1,
    //     //         'sendback_remark' => $request->remark,
    //     //         'remark' => null,
    //     //         'action_taken_by' => $user->id,
    //     //     ]);

    //     //     $previousStep = RequestWorkflowDetails::where('request_id', $request_id)
    //     //         ->where('workflow_step_id', '<', $current->workflow_step_id)
    //     //         ->orderByDesc('workflow_step_id')
    //     //         ->first();

    //     //     if ($previousStep) {

    //     //         // 🔹 If NOT first step → move back to previous approver
    //     //         $previousStep->update(['status' => 'pending']);

    //     //         NotificationService::send(
    //     //             $previousStep->assigned_user_id,
    //     //             'Request Sent Back',
    //     //             "Request {$requestData->request_id} has been sent back by {$user->name}. Remark: {$request->remark}",
    //     //             'request_sendback',
    //     //             $requestData->request_id,
    //     //             'request',
    //     //             'Workflow sendback'
    //     //         );

    //     //     } else {

    //     //         // 🔹 FIRST STEP → Update request table to DRAFT
    //     //         $requestData->update([
    //     //             'status' => 'draft',
    //     //         ]);

    //     //         NotificationService::send(
    //     //             $requestData->user,
    //     //             'Request Sent Back to Draft',
    //     //             "Your request {$requestData->request_id} has been sent back to draft by {$user->name}. Remark: {$request->remark}",
    //     //             'request_sendback',
    //     //             $requestData->request_id,
    //     //             'request',
    //     //             'Workflow sendback to draft'
    //     //         );
    //     //     }

    //     //     $this->syncRequestStatus($request_id);

    //     //     return response()->json([
    //     //         'status' => 'success',
    //     //         'message' => 'Sent back successfully',
    //     //     ]);
    //     // }

    //     if ($request->action === 'sendback') {
    //         $current->update([
    //             'is_sendback' => 1,
    //             'sendback_remark' => $request->remark,
    //             'remark' => null,
    //             'action_taken_by' => $user->id,
    //             'status' => 'sentback',
    //         ]);

    //         $previousStep = RequestWorkflowDetails::where('request_id', $request_id)
    //             ->where('workflow_step_id', '<', $current->workflow_step_id)
    //             ->orderByDesc('workflow_step_id')
    //             ->first();

    //         if ($previousStep) {
    //             $previousStep->update(['status' => 'pending']);
    //             NotificationService::send(
    //                 $previousStep->assigned_user_id,
    //                 'Request Sent Back',
    //                 "Request {$requestData->request_id} has been sent back by {$user->name}. Remark: {$request->remark}",
    //                 'request_sendback',
    //                 $requestData->request_id,
    //                 'request',
    //                 'Workflow sendback'
    //             );
    //         } else {
    //             // FIRST STEP → move request to draft
    //             $requestData->update(['status' => 'draft']);

    //             // Immediately delete all workflow details
    //             RequestWorkflowDetails::where('request_id', $request_id)->delete();

    //             NotificationService::send(
    //                 $requestData->user,
    //                 'Request Sent Back to Draft',
    //                 "Your request {$requestData->request_id} has been sent back to draft by {$user->name}. Remark: {$request->remark}",
    //                 'request_sendback',
    //                 $requestData->request_id,
    //                 'request',
    //                 'Workflow sendback to draft'
    //             );
    //         }

    //         $this->syncRequestStatus($request_id);

    //         return response()->json([
    //             'status' => 'success',
    //             'message' => 'Sent back successfully',
    //         ]);
    //     }
    // }

    private function resetNextStepSendback(string $request_id, int $currentStepId): void
    {
        $nextStep = RequestWorkflowDetails::where('request_id', $request_id)
            ->where('workflow_step_id', '>', $currentStepId)
            ->orderBy('workflow_step_id', 'asc')
            ->first();

        if ($nextStep) {
            //  Reset is_sendback AND status back to pending for ALL records in next step
            RequestWorkflowDetails::where('request_id', $request_id)
                ->where('workflow_step_id', $nextStep->workflow_step_id)
                ->where('is_sendback', 1)
                ->update([
                    'is_sendback' => 0,
                    'status' => 'pending',       //  restore so users can act again
                    'action_taken_by' => null,   //  clear previous action
                ]);
        }
    }

    private function triggerNextStepNotifications($request_id, $current_step_id)
    {
        $currentStep = RequestWorkflowDetails::where('request_id', $request_id)
            ->where('workflow_step_id', $current_step_id)
            ->first();

        if (! $currentStep) {
            return;
        }

        // Find the next step - FIXED: Don't use group_by, use distinct with orderBy
        $nextStep = RequestWorkflowDetails::where('request_id', $request_id)
            ->where('workflow_step_id', '>', $current_step_id)
            ->where('status', 'waiting')
            ->orderBy('workflow_step_id', 'asc')
            ->first();

        if (! $nextStep) {
            return;
        }

        // Get all users in the next step
        $nextStepUsers = RequestWorkflowDetails::where('request_id', $request_id)
            ->where('workflow_step_id', $nextStep->workflow_step_id)
            ->where('status', 'waiting')
            ->get();

        $requestData = ModelsRequest::where('request_id', $request_id)->first();

        foreach ($nextStepUsers as $stepUser) {
            // Update status from 'waiting' to 'pending' for the next step
            $stepUser->update(['status' => 'pending']);

            // Send notification
            NotificationService::send(
                $stepUser->assigned_user_id,
                'New Request Assigned',
                "Request {$request_id} has been assigned to you for approval.",
                'workflow_assigned',
                $request_id,
                'request',
                'Assigned via workflow step'
            );
        }
    }

    public function takeAction(Request $request, $request_id)
    {
        $validated = $request->validate([
            'action' => 'required|in:approve,reject,sendback',
            'remark' => 'nullable|string',
        ]);

        $user = Auth::user();
        if (! $user) {
            return response()->json(['status' => 'error', 'message' => 'Unauthenticated'], 401);
        }

        $current = RequestWorkflowDetails::where('request_id', $request_id)
            ->where('assigned_user_id', $user->id)
            ->where('status', 'pending')
            ->first();

        if (! $current) {
            return response()->json(['status' => 'error', 'message' => 'No pending workflow for you'], 403);
        }

        $requestData = ModelsRequest::where('request_id', $request_id)->first();
        if (! $requestData) {
            return response()->json(['status' => 'error', 'message' => 'Request not found'], 404);
        }

        $logic = strtolower($current->approval_logic);

        // ------------------ AUTO-APPROVE: entity mismatch ------------------ //
        if ($current->assignedUser->entiti_id != $requestData->entiti && in_array($logic, ['single', 'and'])) {
            $current->update([
                'status' => 'approved',
                'remark' => 'Auto-approved: assigned user entity mismatch',
                'action_taken_by' => null,
            ]);

            $this->syncRequestStatus($request_id);

            NotificationService::send(
                $requestData->user,
                'Request Auto-Approved',
                "Your request {$requestData->request_id} has been auto-approved because the assigned user belongs to a different entity.",
                'request_auto_approved',
                $requestData->request_id,
                'request',
                'Workflow auto-approval'
            );

            return response()->json([
                'status' => 'success',
                'message' => 'Step auto-approved due to different entity',
            ]);
        }

        // ------------------ APPROVE ------------------ //
        if ($request->action === 'approve') {
            if ($requestData->amount > $user->loa) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'You cannot approve: LOA too low',
                ], 403);
            }

            if (in_array($logic, ['single', 'and'])) {
                $current->update([
                    'status' => 'approved',
                    'remark' => $request->remark,
                    'action_taken_by' => $user->id,
                ]);

                if ($logic === 'and') {
                    $pendingApprovals = RequestWorkflowDetails::where('request_id', $request_id)
                        ->where('workflow_step_id', $current->workflow_step_id)
                        ->where('status', 'pending')
                        ->count();

                    if ($pendingApprovals === 0) {
                        // Reset is_sendback on the next step
                        $this->resetNextStepSendback($request_id, $current->workflow_step_id);

                        // TRIGGER NOTIFICATIONS FOR NEXT STEP
                        $this->triggerNextStepNotifications($request_id, $current->workflow_step_id);

                        NotificationService::send(
                            $requestData->user,
                            'Request Approved',
                            "Your request {$requestData->request_id} has been approved by all assigned approvers.",
                            'request_approved',
                            $requestData->request_id,
                            'request',
                            'Workflow approval'
                        );
                    }
                } else {
                    $this->resetNextStepSendback($request_id, $current->workflow_step_id);

                    $this->triggerNextStepNotifications($request_id, $current->workflow_step_id);

                    NotificationService::send(
                        $requestData->user,
                        'Request Approved',
                        "Your request {$requestData->request_id} has been approved by {$user->name}.",
                        'request_approved',
                        $requestData->request_id,
                        'request',
                        'Workflow approval'
                    );
                }

            } elseif ($logic === 'or') {
                RequestWorkflowDetails::where('request_id', $request_id)
                    ->where('workflow_step_id', $current->workflow_step_id)
                    ->update([
                        'status' => 'approved',
                        'remark' => $request->remark,
                        'action_taken_by' => $user->id,
                    ]);

                $this->resetNextStepSendback($request_id, $current->workflow_step_id);

                $this->triggerNextStepNotifications($request_id, $current->workflow_step_id);

                NotificationService::send(
                    $requestData->user,
                    'Request Approved',
                    "Your request {$requestData->request_id} has been approved by {$user->name}.",
                    'request_approved',
                    $requestData->request_id,
                    'request',
                    'Workflow approval'
                );
            }

            $this->syncRequestStatus($request_id);

            return response()->json(['status' => 'success', 'message' => 'Approved successfully']);
        }

        // ------------------ REJECT ------------------ //
        if ($request->action === 'reject') {
            RequestWorkflowDetails::where('request_id', $request_id)
                ->where('workflow_step_id', $current->workflow_step_id)
                ->update([
                    'status' => 'rejected',
                    'remark' => $request->remark,
                    'action_taken_by' => $user->id,
                    'is_sendback' => 0,
                    'sendback_remark' => null,
                ]);

            $this->syncRequestStatus($request_id);

            NotificationService::send(
                $requestData->user,
                'Request Rejected',
                "Your request {$requestData->request_id} has been rejected by {$user->name}.",
                'request_rejected',
                $requestData->request_id,
                'request',
                'Workflow rejection'
            );

            return response()->json(['status' => 'success', 'message' => 'Rejected successfully']);
        }

        // ------------------ SENDBACK ------------------ //
        if ($request->action === 'sendback') {

            // mark current step as sendback
            RequestWorkflowDetails::where('request_id', $request_id)
                ->where('workflow_step_id', $current->workflow_step_id)
                ->update([
                    'is_sendback' => 1,
                    'sendback_remark' => $request->remark,
                    'remark' => null,
                    'action_taken_by' => $user->id,
                    'status' => 'sentback',
                ]);

            // convert request to draft
            $requestData->update([
                'status' => 'draft',
            ]);

            // remove workflow steps
            RequestWorkflowDetails::where('request_id', $request_id)->delete();

            NotificationService::send(
                $requestData->user,
                'Request Sent Back',
                "Your request {$requestData->request_id} has been sent back to draft by {$user->name}. Remark: {$request->remark}",
                'request_sendback',
                $requestData->request_id,
                'request',
                'Workflow sendback'
            );

            return response()->json([
                'status' => 'success',
                'message' => 'Request sent back to draft successfully',
            ]);
        }
    }

    /**
     * Sync master request status based on workflow steps
     */
    private function syncRequestStatus(string $requestId)
    {
        $req = ModelsRequest::where('request_id', $requestId)->first();
        if (! $req) {
            return;
        }

        $steps = RequestWorkflowDetails::where('request_id', $requestId)->get();

        if ($steps->isEmpty()) {
            // If no workflow steps exist, set to draft
            $req->update(['status' => 'draft']);

            return;
        }

        if ($steps->contains('status', 'rejected')) {
            $req->update(['status' => 'rejected']);

            return;
        }

        if ($steps->every(fn ($s) => $s->status === 'pending')) {
            $req->update(['status' => 'submitted']);

            return;
        }

        if ($steps->contains('status', 'approved') && $steps->contains('status', 'pending')) {
            $req->update(['status' => 'in_approval']);

            return;
        }

        if ($steps->every(fn ($s) => $s->status === 'approved')) {
            $req->update(['status' => 'approve']);

            return;
        }
    }
}
