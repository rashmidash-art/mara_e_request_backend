<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Document;
use App\Models\Entiti;
use App\Models\Request as ModelsRequest;
use App\Models\RequestDocument;
use App\Models\RequestWorkflowDetails;
use App\Models\User;
use App\Models\WorkFlow;
use App\Models\WorkflowRoleAssign;
use App\Models\WorkflowStep;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class CreateRequestController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $auth = Auth::user();
            $isEntityLogin = $auth instanceof Entiti;
            $isSuperAdmin = ! $isEntityLogin && isset($auth->user_type) && $auth->user_type == 0;
            $query = ModelsRequest::with([
                'categoryData:id,name',
                'entityData:id,name',
                'userData:id,name',
                'requestTypeData:id,name',
                'departmentData:id,name',
                'supplierData:id,name',
                'budgetCode:id,budget_code',
                'documents:id,request_id,document_id,document', // Eager load the documents
                'currentWorkflowRole' => function ($q) {
                    $q->select(
                        'id',
                        'request_id',
                        'workflow_role_id',
                        'assigned_user_id',
                        'status',
                        'workflow_step_id'
                    );
                },
                'currentWorkflowRole.role:id,name',
                'currentWorkflowRole.assignedUser:id,name',
                'currentWorkflowRole.workflowStep:id,name',
            ])->orderByDesc('id');
            if ($isSuperAdmin) {
                $requests = $query->get();
            } elseif ($isEntityLogin) {
                $requests = $query->where('entiti', $auth->id)->get();
            } else {
                $userId = $auth->id;

                $requests = $query->whereHas('currentWorkflowRole', function ($q) use ($userId) {
                    $q->where('assigned_user_id', $userId)
                        ->where('status', 'pending');
                })->get();
            }

            $data = $requests->map(function ($req) {

                $workflow = $req->currentWorkflowRole;

                return [
                    'request_id' => $req->request_id,
                    'amount' => $req->amount,
                    'priority' => $req->priority,
                    'description' => $req->description,
                    'status' => $req->status,
                    'created_at' => $req->created_at?->format('Y-m-d H:i:s'),

                    'user' => [
                        'id' => $req->user,
                        'name' => $req->userData?->name,
                    ],

                    'category' => [
                        'id' => $req->category,
                        'name' => $req->categoryData?->name,
                    ],

                    'entity' => [
                        'id' => $req->entiti,
                        'name' => $req->entityData?->name,
                    ],

                    'budget_code' => [
                        'id' => $req->budget_code,
                        'name' => $req->budgetCode?->budget_code,
                    ],

                    'requested_by' => [
                        'id' => $req->user,
                        'name' => $req->userData?->name,
                    ],

                    'request_type' => [
                        'id' => $req->request_type,
                        'name' => $req->requestTypeData?->name,
                    ],

                    'workflow' => [
                        'step' => $workflow?->workflowStep?->name,
                        'role' => $workflow?->role?->name,
                        'assigned_user' => $workflow?->assignedUser?->name,
                        'status' => $workflow?->status,
                    ],

                    // Include documents with only document_id and document name
                    // 'documents' => $req->documents->map(function ($doc) {
                    //     return [
                    //         'document_id' => $doc->document_id,
                    //         'document'    => $doc->document
                    //     ];
                    // }),

                    'documents' => $req->documents->map(function ($doc) {
                        // Extract the actual filename after the last underscore
                        $filename = last(explode('_', $doc->document));

                        return [
                            'document_id' => $doc->document_id,
                            'document' => $filename,
                        ];
                    }),
                ];
            });

            return response()->json([
                'status' => 'success',
                'data' => $data,
            ]);
        } catch (\Exception $e) {

            Log::error('Request index failed: '.$e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch requests',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function store(Request $request)
    {
        try {

            // ------------------------- VALIDATION ------------------------- //
            $validated = $request->validate([
                'entiti' => 'nullable|integer',
                'user' => 'nullable|integer',
                'request_type' => 'nullable|integer',
                'category' => 'nullable|integer',
                'department' => 'nullable|integer',
                'amount' => 'nullable|string',
                'description' => 'nullable|string',
                'supplier_id' => 'nullable|integer',
                'expected_date' => 'nullable|string',
                'priority' => 'nullable|string',
                'behalf_of' => 'nullable|in:0,1',
                'behalf_of_department' => 'required_if:behalf_of,1',
                'business_justification' => 'nullable|string',
                'status' => 'nullable|in:submitted,draft,deleted,withdraw',
                'attachments' => 'nullable|array',
                'attachments.*.document_id' => 'required|integer',
                'attachments.*.file' => 'required|file|max:10240',
                'budget_code' => 'required|exists:budget_codes,id',
            ]);

            // ------------------------- CREATE REQUEST ID ------------------------- //
            $year = date('Y');
            $last = ModelsRequest::whereYear('created_at', $year)->orderBy('id', 'desc')->first();
            $nextNumber = $last ? $last->id + 1 : 1;
            $request_no = "REQ-{$year}-".str_pad($nextNumber, 3, '0', STR_PAD_LEFT);

            // ------------------------- INSERT REQUEST MASTER ------------------------- //
            $req = ModelsRequest::create([
                'request_id' => $request_no,
                'entiti' => $request->entiti,
                'user' => $request->user,
                'request_type' => $request->request_type,
                'category' => $request->category,
                'department' => $request->department,
                'budget_code' => $request->budget_code,
                'amount' => $request->amount,
                'description' => $request->description,
                'supplier_id' => $request->supplier_id,
                'expected_date' => $request->expected_date,
                'priority' => $request->priority,
                'behalf_of' => $request->behalf_of,
                'behalf_of_department' => $request->behalf_of_department,
                'business_justification' => $request->business_justification,
                'status' => $request->status ?? 'draft',
            ]);

            // ------------------------- ATTACHMENTS ------------------------- //
            if (! empty($request->attachments)) {
                foreach ($request->attachments as $index => $doc) {

                    $file = $request->file("attachments.$index.file");
                    if (! $file) {
                        continue;
                    }

                    $departmentName = Department::find($req->department)->name ?? 'unknown';
                    $departmentName = str_replace(' ', '_', strtolower($departmentName));

                    $newFileName = $req->request_id.'_'.$req->entiti.'_'.$departmentName.'_'.$file->getClientOriginalName();
                    $file->storeAs('requestdocuments', $newFileName, 'public');

                    RequestDocument::create([
                        'request_id' => $req->request_id,
                        'document_id' => $doc['document_id'],
                        'document' => $newFileName,
                    ]);
                }
            }

            // ------------------------- FETCH WORKFLOW ------------------------- //
            $workflow = WorkFlow::where('categori_id', $req->category)
                ->where('request_type_id', $req->request_type)
                ->first();

            if (! $workflow) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Workflow not configured',
                ], 422);
            }

            // ------------------------- WORKFLOW DETAILS ------------------------- //
            $steps = WorkflowStep::where('workflow_id', $workflow->id)
                ->orderBy('order_id', 'asc')
                ->get();

            foreach ($steps as $step) {

                $roleAssigns = WorkflowRoleAssign::where('workflow_id', $workflow->id)
                    ->where('step_id', $step->id)
                    ->get();

                foreach ($roleAssigns as $roleAssign) {

                    $approvalLogic = strtolower($roleAssign->approval_logic);
                    $users = collect();

                    // -------- SPECIFIC USER LOGIC -------- //
                    if ($roleAssign->specific_user == 1 && $roleAssign->user_id) {

                        $userIds = json_decode($roleAssign->user_id, true);
                        $userIds = is_array($userIds) ? $userIds : [$roleAssign->user_id];

                        $users = User::whereIn('id', $userIds)->get();

                    } else {
                        // -------- ROLE BASED USERS -------- //
                        $users = User::whereHas('roles', function ($q) use ($roleAssign) {
                            $q->where('roles.id', $roleAssign->role_id);
                        })->get();
                    }

                    if ($users->isEmpty()) {
                        continue;
                    }

                    // -------- AND LOGIC -------- //
                    if ($approvalLogic === 'and') {

                        foreach ($users as $user) {
                            RequestWorkflowDetails::create([
                                'request_id' => $req->request_id,
                                'workflow_id' => $workflow->id,
                                'workflow_step_id' => $step->id,
                                'workflow_role_id' => $roleAssign->role_id,
                                'assigned_user_id' => $user->id,
                                'status' => 'pending',
                                'is_sendback' => 0,
                            ]);
                        }

                    } else {
                        // -------- SINGLE / OR LOGIC -------- //
                        RequestWorkflowDetails::create([
                            'request_id' => $req->request_id,
                            'workflow_id' => $workflow->id,
                            'workflow_step_id' => $step->id,
                            'workflow_role_id' => $roleAssign->role_id,
                            'assigned_user_id' => $users->first()->id,
                            'status' => 'pending',
                            'is_sendback' => 0,
                        ]);
                    }
                }
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Request created successfully',
                'data' => $req,
            ], 201);

        } catch (\Exception $e) {

            Log::error('Request Store Error', ['error' => $e->getMessage()]);

            return response()->json([
                'status' => 'error',
                'message' => 'Something went wrong',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /** Create a new request */
    public function show($id)
    {
        try {
            $req = ModelsRequest::find($id);

            if (! $req) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Request not found',
                ], 404);
            }

            $documents = RequestDocument::where('request_id', $req->id)->get();

            return response()->json([
                'status' => 'success',
                'message' => 'Request details retrieved',
                'data' => [
                    'request' => $req,
                    'documents' => $documents,
                ],
            ], 200);
        } catch (\Exception $e) {
            Log::error('Request Fetch Error', ['error' => $e->getMessage()]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch request',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /** Update request */
    public function update(Request $request, $id)
    {
        //  Find by request_id (string)
        $req = ModelsRequest::where('request_id', $id)->first();

        if (! $req) {
            return response()->json([
                'status' => 'error',
                'message' => 'Request not found',
            ], 404);
        }

        //  Handle withdraw separately
        if ($request->status === 'withdraw') {

            // Allow withdraw ONLY if submitted
            if ($req->status !== 'submitted') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Only submitted requests can be withdrawn',
                ], 422);
            }

            $req->update(['status' => 'withdraw']);

            // Optional: cancel workflow
            RequestWorkflowDetails::where('request_id', $req->request_id)
                ->update(['status' => 'cancelled']);

            return response()->json([
                'status' => 'success',
                'message' => 'Request withdrawn successfully',
            ]);
        }

        // 🔹 Normal update flow (existing logic)
        $validated = $request->validate([
            'entiti' => 'nullable',
            'user' => 'nullable|integer',
            'request_type' => 'nullable|integer',
            'category' => 'nullable|integer',
            'department' => 'nullable|integer',
            'amount' => 'nullable|string',
            'description' => 'nullable|string',
            'supplier_id' => 'nullable|integer',
            'expected_date' => 'nullable|string',
            'priority' => 'nullable|string',
            'behalf_of' => 'nullable|integer|in:0,1',
            'behalf_of_department' => 'nullable|integer',
            'business_justification' => 'nullable|string',
            'status' => 'nullable|in:submitted,draft,deleted',
        ]);

        $req->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Request updated successfully',
            'data' => $req,
        ]);
    }

    /** Delete request */
    public function destroy($id)
    {
        try {
            $req = ModelsRequest::find($id);

            if (! $req) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Request not found',
                ], 404);
            }

            RequestDocument::where('request_id', $req->id)->delete();
            $req->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Request deleted successfully',
            ], 200);
        } catch (\Exception $e) {
            Log::error('Request Delete Failed', ['error' => $e->getMessage()]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete request',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function myActionableRequests()
    {
        $user = Auth::user();

        $roleIds = $user->roles()->pluck('id');

        $requests = RequestWorkflowDetails::whereIn('workflow_role_id', $roleIds)
            ->where('status', 'pending')
            ->with('request')
            ->get()
            ->groupBy('request_id');

        return response()->json([
            'status' => 'success',
            'data' => $requests,
        ]);
    }

    public function requestDetailsAll(Request $request)
    {
        try {
            $auth = Auth::user();
            $userId = $auth->id;
            $isEntityLogin = $auth instanceof Entiti;
            $isSuperAdmin = (! $isEntityLogin && isset($auth->user_type) && $auth->user_type == 0);

            // Fetching requests and their details
            $baseQuery = ModelsRequest::with([
                'categoryData:id,name',
                'entityData:id,name',
                'userData:id,name',
                'requestTypeData:id,name',
                'departmentData:id,name',
                'supplierData:id,name',
                'documents:id,request_id,document_id,document',
                'workflowHistory' => function ($q) {
                    $q->with(['role', 'assignedUser', 'workflowStep'])->orderBy('id', 'asc');
                },
            ]);

            if ($isSuperAdmin) {
                $requests = $baseQuery->orderByDesc('id')->get();
            } elseif ($isEntityLogin) {
                $requests = $baseQuery->where('entiti', $auth->id)->orderByDesc('id')->get();
            } else {
                $requests = $baseQuery->where(function ($q) use ($userId) {
                    $q->where('user', $userId)
                        ->orWhereHas('currentWorkflowRole', function ($w) use ($userId) {
                            $w->where('assigned_user_id', $userId)
                                ->where('status', 'pending');
                        });
                })->orderByDesc('id')->get();
            }

            // Count query for pending, approved, and rejected workflow steps
            $countQuery = ModelsRequest::query();
            if ($isEntityLogin) {
                $countQuery->where('entiti', $auth->id);
            } elseif (! $isSuperAdmin) {
                $countQuery->where(function ($q) use ($userId) {
                    $q->where('user', $userId)
                        ->orWhereHas('currentWorkflowRole', function ($w) use ($userId) {
                            $w->where('assigned_user_id', $userId)
                                ->where('status', 'pending');
                        });
                });
            }

            $counts = [
                'total' => $countQuery->count(),
                'draft' => $countQuery->clone()->where('status', 'draft')->count(),
                'submitted' => $countQuery->clone()->where('status', 'submitted')->count(),
                'approved' => $countQuery->clone()->where('status', 'approved')->count(),
                'rejected' => $countQuery->clone()->where('status', 'rejected')->count(),
                'pending' => $countQuery->clone()->whereHas('workflowHistory', function ($query) {
                    // Check for 'pending' status in the workflow history
                    $query->where('status', 'pending');
                })->count(),
            ];

            // Get total amount per user, per entity, and for the admin
            $userTotalAmount = ModelsRequest::where('user', $userId)->sum('amount');

            $entityTotalAmount = ModelsRequest::where('entiti', $auth->id)
                ->sum('amount'); // If user is associated with an entity, calculate entity total

            $adminTotalAmount = ModelsRequest::sum('amount'); // Admin total (all requests)

            // Map through the requests and build the response data
            $data = $requests->map(function ($req) {
                $workflowHistory = $req->workflowHistory;

                // Build the approval timeline with the first step as submission by the user
                $approvalTimeline = collect([
                    [
                        'stage' => 'Submitted',
                        'role' => 'You',
                        'assigned_user' => $req->userData?->name ?? 'You',
                        'status' => 'submitted',
                        'date' => $req->created_at?->format('Y-m-d'),
                    ],
                ])->concat(
                    $workflowHistory->map(function ($step) {
                        return [
                            'stage' => $step->workflowStep?->name ?? 'N/A',
                            'role' => $step->role?->name ?? 'N/A',
                            'assigned_user' => $step->assignedUser?->name ?? '—',
                            'status' => $step->status,
                            'date' => $step->updated_at?->format('Y-m-d') ?? '-',
                        ];
                    })
                );

                // Get the final status of the request based on its workflow steps
                $finalStatus = $req->getFinalStatus();

                return [
                    'request_id' => $req->request_id,
                    'amount' => $req->amount,
                    'priority' => $req->priority,
                    'description' => $req->description,
                    'status' => $req->status,
                    'final_status' => $finalStatus['final_status'],
                    'pending_by' => $finalStatus['pending_by'],
                    'created_at' => $req->created_at?->format('Y-m-d H:i:s'),

                    'category' => [
                        'id' => $req->category,
                        'name' => $req->categoryData?->name,
                    ],

                    'entity' => [
                        'id' => $req->entiti,
                        'name' => $req->entityData?->name,
                    ],

                    'requested_by' => [
                        'id' => $req->user,
                        'name' => $req->userData?->name,
                    ],

                    'request_type' => [
                        'id' => $req->request_type,
                        'name' => $req->requestTypeData?->name,
                    ],

                    'department' => [
                        'id' => $req->department,
                        'name' => $req->departmentData?->name,
                    ],

                    'supplier' => [
                        'id' => $req->supplier_id,
                        'name' => $req->supplierData?->name,
                    ],

                    'workflow_history' => $approvalTimeline->values(), // Reindex keys

                    'documents' => $req->documents->map(function ($doc) {
                        $filename = last(explode('_', $doc->document));

                        return [
                            'document_id' => $doc->document_id,
                            'document' => $filename,
                        ];
                    }),
                ];
            });

            // Return the JSON response with the counts, total amounts, and data
            return response()->json([
                'status' => 'success',
                'counts' => $counts,
                'total_amount' => [
                    'user_total_amount' => $userTotalAmount,
                    'entity_total_amount' => $entityTotalAmount,
                    'admin_total_amount' => $adminTotalAmount,
                ],
                'data' => $data,
            ]);

        } catch (\Exception $e) {
            Log::error('Request index failed: '.$e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch requests',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
