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
            'approved' => $data->where('status', 'approved')->count(),
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

        $logic = strtolower($current->approval_logic); // single / or / and
        $requestData = ModelsRequest::where('request_id', $request_id)->first();

        if (! $requestData) {
            return response()->json(['status' => 'error', 'message' => 'Request not found'], 404);
        }

        // ------------------ APPROVE ------------------ //
        if ($request->action === 'approve') {

            if ($requestData->amount > $user->loa) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'You cannot approve: LOA too low',
                ], 403);
            }

            if ($logic === 'single' || $logic === 'and') {
                $current->update([
                    'status' => 'approved',
                    'remark' => $request->remark,
                    'action_taken_by' => $user->id,
                ]);

                // For AND logic, notify requestor only after all assigned users approve
                if ($logic === 'and') {
                    $pendingApprovals = RequestWorkflowDetails::where('request_id', $request_id)
                        ->where('workflow_step_id', $current->workflow_step_id)
                        ->where('status', 'pending')
                        ->count();

                    if ($pendingApprovals === 0) {
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
                    // single logic
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
                // OR logic: notify requestor immediately
                RequestWorkflowDetails::where('request_id', $request_id)
                    ->where('workflow_step_id', $current->workflow_step_id)
                    ->update([
                        'status' => 'approved',
                        'remark' => $request->remark,
                        'action_taken_by' => $user->id,
                    ]);

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
            // --- NOTIFY REQUESTOR ---
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
            $current->update([
                'is_sendback' => 1,
                'sendback_remark' => $request->remark,
                'remark' => null,
                'action_taken_by' => $user->id,
            ]);

            $previousStep = RequestWorkflowDetails::where('request_id', $request_id)
                ->where('workflow_step_id', '<', $current->workflow_step_id)
                ->orderByDesc('workflow_step_id')
                ->first();

            if ($previousStep) {
                $previousStep->update(['status' => 'pending']);

                NotificationService::send(
                    $previousStep->assigned_user_id,
                    'Request Sent Back',
                    "Request {$requestData->request_id} has been sent back by {$user->name}. Remark: {$request->remark}",
                    'request_sendback',
                    $requestData->request_id,
                    'request',
                    'Workflow sendback'
                );

            } else {
                NotificationService::send(
                    $requestData->user,
                    'Request Sent Back',
                    "Your request {$requestData->request_id} has been sent back by {$user->name}. Remark: {$request->remark}",
                    'request_sendback',
                    $requestData->request_id,
                    'request',
                    'Workflow sendback'
                );
            }

            $this->syncRequestStatus($request_id);

            return response()->json(['status' => 'success', 'message' => 'Sent back successfully']);
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

        // If any rejected → rejected
        if ($steps->contains('status', 'rejected')) {
            $req->update(['status' => 'rejected']);

            return;
        }

        // If all pending → submitted
        if ($steps->every(fn ($s) => $s->status === 'pending')) {
            $req->update(['status' => 'submitted']);

            return;
        }

        // If some approved & some pending → in_approval
        if ($steps->contains('status', 'approved') && $steps->contains('status', 'pending')) {
            $req->update(['status' => 'in_approval']);

            return;
        }

        // If all approved → approved
        if ($steps->every(fn ($s) => $s->status === 'approved')) {
            $req->update(['status' => 'approve']);

            return;
        }
    }
}
