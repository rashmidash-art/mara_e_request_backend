<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Document;
use App\Models\Entiti;
use App\Models\Request as ModelsRequest;
use App\Models\RequestDetailsDocuments;
use App\Models\RequestDocument;
use App\Models\RequestWorkflowDetails;
use App\Models\User;
use App\Models\WorkFlow;
use App\Models\WorkflowRoleAssign;
use App\Models\WorkflowStep;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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
                    'id' => $req->id,
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

            RequestDetailsDocuments::create([
                'request_details_id' => $req->id,
                'request_id' => $req->request_id,

                'is_po_created' => 0,
                'po_number' => null,
                'po_date' => null,
                'po_documents' => null,

                'is_delivery_completed' => 0,
                'delivery_completed_number' => null,
                'delivery_completed_date' => null,
                'delivery_completed_documents' => null,

                'is_payment_completed' => 0,
                'payment_completed_number' => null,
                'payment_completed_date' => null,
                'payment_completed_documents' => null,

                'status' => 'pending',
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

            // ------------------------- DRAFT: SKIP WORKFLOW ------------------------- //
            if ($req->status === 'draft') {
                return response()->json([
                    'status' => 'success',
                    'message' => 'Draft saved successfully',
                    'data' => $req,
                ], 201);
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

        Log::info('FILES', $request->allFiles());
        Log::info('HAS PO FILE', [
            'has_po' => $request->hasFile('po_documents'),
        ]);

        $req = ModelsRequest::find($id);

        if (! $req) {
            return response()->json([
                'status' => 'error',
                'message' => 'Request not found',
            ], 404);
        }

        /**
         * ===============================
         * WITHDRAW REQUEST
         * ===============================
         */
        if ($request->status === 'withdraw') {
            if ($req->status !== 'submitted') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Only submitted requests can be withdrawn',
                ], 422);
            }

            DB::transaction(function () use ($req) {
                $req->update([
                    'status' => 'withdraw',
                    'updated_at' => now(),
                ]);

                RequestWorkflowDetails::where('request_id', $req->request_id)
                    ->where('status', 'pending')
                    ->update([
                        'status' => 'withdraw',
                        'updated_at' => now(),
                    ]);
            });

            return response()->json([
                'status' => 'success',
                'message' => 'Request withdrawn successfully',
            ]);
        }

        /**
         * ===============================
         * DOCUMENT UPLOAD
         * ===============================
         */
        if (
            $request->hasFile('po_documents') ||
            $request->hasFile('delivery_completed_documents') ||
            $request->hasFile('payment_completed_documents')
        ) {
            DB::transaction(function () use ($request, $req) {

                $doc = RequestDetailsDocuments::updateOrCreate(
                    ['request_id' => $req->request_id],
                    ['request_details_id' => $req->id]
                );

                /* ---------- PO ---------- */
                if ($request->hasFile('po_documents')) {
                    $file = $request->file('po_documents');
                    $name = 'po_'.$req->request_id.'_'.$file->getClientOriginalName();
                    $file->storeAs('request_documents', $name, 'public');

                    $doc->update([
                        'is_po_created' => 1,
                        'po_number' => $request->po_number,
                        'po_date' => $request->po_date,
                        'po_documents' => $name,
                    ]);
                }

                /* ---------- DELIVERY ---------- */
                if ($request->hasFile('delivery_completed_documents')) {
                    $file = $request->file('delivery_completed_documents');
                    $name = 'delivery_'.$req->request_id.'_'.$file->getClientOriginalName();
                    $file->storeAs('request_documents', $name, 'public');

                    $doc->update([
                        'is_delivery_completed' => 1,
                        'delivery_completed_number' => $request->delivery_completed_number,
                        'delivery_completed_date' => $request->delivery_completed_date,
                        'delivery_completed_documents' => $name,
                    ]);
                }

                /* ---------- PAYMENT ---------- */
                if ($request->hasFile('payment_completed_documents')) {
                    $file = $request->file('payment_completed_documents');
                    $name = 'payment_'.$req->request_id.'_'.$file->getClientOriginalName();
                    $file->storeAs('request_documents', $name, 'public');

                    $doc->update([
                        'is_payment_completed' => 1,
                        'payment_completed_number' => $request->payment_completed_number,
                        'payment_completed_date' => $request->payment_completed_date,
                        'payment_completed_documents' => $name,
                    ]);

                    Log::info('DOC MODEL', [
                        'table' => $doc->getTable(),
                        'id' => $doc->id,
                        'connection' => $doc->getConnectionName(),
                    ]);
                }

                $doc->status = strtolower($doc->getCurrentStatus());

                Log::info('DB NAME', [
                    DB::connection()->getDatabaseName(),
                ]);
                $doc->save();
            });

            return response()->json([
                'status' => 'success',
                'message' => 'Documents uploaded successfully',
            ]);
        }

        /**
         * ===============================
         * NORMAL UPDATE
         * ===============================
         */
        $validated = $request->validate([
            'amount' => 'nullable|string',
            'description' => 'nullable|string',
            'priority' => 'nullable|string',
            'status' => 'nullable|in:submitted,draft,deleted',
        ]);

        $req->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Request updated successfully',
        ]);
    }

    // public function update(Request $request, $id)
    // {
    //     // Find by request_id (string)
    //     $req = ModelsRequest::where('id', $id)->first();

    //     if (! $req) {
    //         return response()->json([
    //             'status' => 'error',
    //             'message' => 'Request not found',
    //         ], 404);
    //     }

    //     /**
    //      * ===============================
    //      * WITHDRAW REQUEST (SPECIAL FLOW)
    //      * ===============================
    //      */
    //     if ($request->status === 'withdraw') {

    //         // Allow withdraw ONLY when submitted
    //         if ($req->status !== 'submitted') {
    //             return response()->json([
    //                 'status' => 'error',
    //                 'message' => 'Only submitted requests can be withdrawn',
    //             ], 422);
    //         }

    //         DB::transaction(function () use ($req) {

    //             //  Update request master
    //             $req->update([
    //                 'status' => 'withdraw',
    //                 'updated_at' => now(),
    //             ]);

    //             //  Update ONLY pending workflow steps
    //             RequestWorkflowDetails::where('request_id', $req->request_id)
    //                 ->where('status', 'pending')
    //                 ->update([
    //                     'status' => 'withdraw',
    //                     'updated_at' => now(),
    //                 ]);
    //         });

    //         return response()->json([
    //             'status' => 'success',
    //             'message' => 'Request withdrawn successfully',
    //         ]);
    //     }

    //     if ($req->status === 'submitted') {
    //         return response()->json([
    //             'status' => 'error',
    //             'message' => 'Submitted requests cannot be edited',
    //         ], 422);
    //     }

    //     /**
    //      * ===============================
    //      * NORMAL UPDATE (DRAFT / SUBMIT)
    //      * ===============================
    //      */
    //     $validated = $request->validate([
    //         'entiti' => 'nullable',
    //         'user' => 'nullable|integer',
    //         'request_type' => 'nullable|integer',
    //         'category' => 'nullable|integer',
    //         'department' => 'nullable|integer',
    //         'budget_code' => 'nullable|integer',
    //         'amount' => 'nullable|string',
    //         'description' => 'nullable|string',
    //         'supplier_id' => 'nullable|integer',
    //         'expected_date' => 'nullable|string',
    //         'priority' => 'nullable|string',
    //         'behalf_of' => 'nullable|integer|in:0,1',
    //         'behalf_of_department' => 'nullable|integer',
    //         'business_justification' => 'nullable|string',
    //         'status' => 'nullable|in:submitted,draft,deleted',
    //     ]);

    //     $req->update($validated);
    //     Log::info('UPDATE PAYLOAD', $request->all());

    //     return response()->json([
    //         'status' => 'success',
    //         'message' => 'Request updated successfully',
    //         'data' => $req,
    //     ]);
    // }

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

            $baseQuery = ModelsRequest::with([
                'categoryData:id,name',
                'entityData:id,name',
                'userData:id,name',
                'requestTypeData:id,name',
                'departmentData:id,name',
                'supplierData:id,name',
                'documents:id,request_id,document_id,document',
                'workflowHistory' => function ($q) {
                    $q->with(['role', 'assignedUser', 'workflowStep'])
                        ->orderBy('id', 'asc');

                },

                'requestDetailsDocuments:id,request_id,is_po_created,is_delivery_completed,is_payment_completed,po_number,po_date,po_documents,delivery_completed_number,delivery_completed_date,delivery_completed_documents,payment_completed_number,payment_completed_date,payment_completed_documents',

            ]);

            if ($isSuperAdmin) {
                $requests = $baseQuery->orderByDesc('id')->get();
            } elseif ($isEntityLogin) {
                $requests = $baseQuery
                    ->where('entiti', $auth->id)
                    ->orderByDesc('id')
                    ->get();
            } else {
                $requests = $baseQuery
                    ->where(function ($q) use ($userId) {
                        $q->where('user', $userId)
                            ->orWhereHas('currentWorkflowRole', function ($w) use ($userId) {
                                $w->where('assigned_user_id', $userId)
                                    ->where('status', 'pending');
                            });
                    })
                    ->orderByDesc('id')
                    ->get();
            }

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
                'draft' => (clone $countQuery)->where('status', 'draft')->count(),
                'submitted' => (clone $countQuery)->where('status', 'submitted')->count(),
                'approved' => (clone $countQuery)->where('status', 'approved')->count(),
                'rejected' => (clone $countQuery)->where('status', 'rejected')->count(),
                'pending' => (clone $countQuery)->whereHas('workflowHistory', function ($q) {
                    $q->where('status', 'pending');
                })->count(),
            ];

            $userTotalAmount = ModelsRequest::where('user', $userId)->sum('amount');
            $entityTotalAmount = ModelsRequest::where('entiti', $auth->id)->sum('amount');
            $adminTotalAmount = ModelsRequest::sum('amount');

            $data = $requests->map(function ($req) {

                $workflowHistory = $req->workflowHistory;

                // Group by workflow step ID to show step only once
                $groupedSteps = $workflowHistory
                    ->groupBy('workflow_step_id')
                    ->map(function ($steps) {
                        $first = $steps->first();

                        // Get the last user who approved/rejected
                        $lastAction = $steps
                            ->whereIn('status', ['approved', 'rejected'])
                            ->sortByDesc('updated_at')
                            ->first();

                        return [
                            'step' => $first->workflowStep?->name ?? 'N/A', // Step name
                            'stage' => $first->workflowStep?->name ?? 'N/A',
                            'role' => $steps
                                ->pluck('role.name')
                                ->filter()
                                ->unique()
                                ->values()
                                ->join(', '),
                            'assigned_user' => $lastAction?->assignedUser?->name ?? '—',
                            'status' => $lastAction?->status ?? 'pending',
                            'date' => $lastAction?->updated_at?->format('Y-m-d') ?? '-',
                        ];
                    })
                    ->values();

                // Final approval timeline including submission as first stage
                $approvalTimeline = collect([
                    [
                        'stage' => 'Submitted',
                        'role' => 'Requester',
                        'assigned_user' => $req->userData?->name ?? 'You',
                        'status' => 'submitted',
                        'date' => $req->created_at?->format('Y-m-d'),
                    ],
                ])->concat($groupedSteps);

                $finalStatus = $req->getFinalStatus();

                $currentStage = match ($finalStatus['final_status']) {
                    'withdraw' => 'Withdrawn',
                    'approved' => 'Completed',
                    'rejected' => 'Rejected',
                    default => $finalStatus['pending_by'],
                };

                return [
                    'id' => $req->id,
                    'request_id' => $req->request_id,
                    'amount' => $req->amount,
                    'priority' => $req->priority,
                    'description' => $req->description,
                    'status' => $req->status,
                    'final_status' => $finalStatus['final_status'],
                    'pending_by' => $finalStatus['pending_by'],
                    'current_stage' => $currentStage,
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
                    'workflow_history' => $approvalTimeline->values(),
                    'documents' => $req->documents->map(function ($doc) {
                        return [
                            'document_id' => $doc->document_id,
                            'document' => last(explode('_', $doc->document)),
                            'url' => url('storage/requestdocuments/'.$doc->document),
                        ];
                    }),

                    'status_flags' => [
                        'is_po_created' => $req->requestDetailsDocuments?->is_po_created ?? 0,
                        'is_delivery_completed' => $req->requestDetailsDocuments?->is_delivery_completed ?? 0,
                        'is_payment_completed' => $req->requestDetailsDocuments?->is_payment_completed ?? 0,
                        'po_number' => $req->requestDetailsDocuments?->po_number,
                        'po_date' => $req->requestDetailsDocuments?->po_date,
                        'po_documents' => $req->requestDetailsDocuments?->po_documents,
                        'delivery_completed_number' => $req->requestDetailsDocuments?->delivery_completed_number,
                        'delivery_completed_date' => $req->requestDetailsDocuments?->delivery_completed_date,
                        'delivery_completed_documents' => $req->requestDetailsDocuments?->delivery_completed_documents,
                        'payment_completed_number' => $req->requestDetailsDocuments?->payment_completed_number,
                        'payment_completed_date' => $req->requestDetailsDocuments?->payment_completed_date,
                        'payment_completed_documents' => $req->requestDetailsDocuments?->payment_completed_documents,
                    ],
                ];
            });

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

    // public function requestDetailsAll(Request $request)
    // {
    //     try {
    //         $auth = Auth::user();
    //         $userId = $auth->id;
    //         $isEntityLogin = $auth instanceof Entiti;
    //         $isSuperAdmin = (! $isEntityLogin && isset($auth->user_type) && $auth->user_type == 0);
    //         $baseQuery = ModelsRequest::with([
    //             'categoryData:id,name',
    //             'entityData:id,name',
    //             'userData:id,name',
    //             'requestTypeData:id,name',
    //             'departmentData:id,name',
    //             'supplierData:id,name',
    //             'documents:id,request_id,document_id,document',
    //             'workflowHistory' => function ($q) {
    //                 $q->with(['role', 'assignedUser', 'workflowStep'])
    //                     ->orderBy('id', 'asc');
    //             },
    //         ]);
    //         if ($isSuperAdmin) {
    //             $requests = $baseQuery->orderByDesc('id')->get();
    //         } elseif ($isEntityLogin) {
    //             $requests = $baseQuery
    //                 ->where('entiti', $auth->id)
    //                 ->orderByDesc('id')
    //                 ->get();
    //         } else {
    //             $requests = $baseQuery
    //                 ->where(function ($q) use ($userId) {
    //                     $q->where('user', $userId)
    //                         ->orWhereHas('currentWorkflowRole', function ($w) use ($userId) {
    //                             $w->where('assigned_user_id', $userId)
    //                                 ->where('status', 'pending');
    //                         });
    //                 })
    //                 ->orderByDesc('id')
    //                 ->get();
    //         }
    //         $countQuery = ModelsRequest::query();

    //         if ($isEntityLogin) {
    //             $countQuery->where('entiti', $auth->id);
    //         } elseif (! $isSuperAdmin) {
    //             $countQuery->where(function ($q) use ($userId) {
    //                 $q->where('user', $userId)
    //                     ->orWhereHas('currentWorkflowRole', function ($w) use ($userId) {
    //                         $w->where('assigned_user_id', $userId)
    //                             ->where('status', 'pending');
    //                     });
    //             });
    //         }

    //         $counts = [
    //             'total' => $countQuery->count(),
    //             'draft' => (clone $countQuery)->where('status', 'draft')->count(),
    //             'submitted' => (clone $countQuery)->where('status', 'submitted')->count(),
    //             'approved' => (clone $countQuery)->where('status', 'approved')->count(),
    //             'rejected' => (clone $countQuery)->where('status', 'rejected')->count(),
    //             'pending' => (clone $countQuery)->whereHas('workflowHistory', function ($q) {
    //                 $q->where('status', 'pending');
    //             })->count(),
    //         ];
    //         $userTotalAmount = ModelsRequest::where('user', $userId)->sum('amount');
    //         $entityTotalAmount = ModelsRequest::where('entiti', $auth->id)->sum('amount');
    //         $adminTotalAmount = ModelsRequest::sum('amount');
    //         $data = $requests->map(function ($req) {

    //             $workflowHistory = $req->workflowHistory;
    //             $approvalTimeline = collect([
    //                 [
    //                     'step' => $step->workflowStep?->name ?? 'N/A',
    //                     'stage' => 'Submitted',
    //                     'role' => 'You',
    //                     'assigned_user' => $req->userData?->name ?? 'You',
    //                     'status' => 'submitted',
    //                     'date' => $req->created_at?->format('Y-m-d'),
    //                 ],
    //             ])->concat(
    //                 $workflowHistory->map(function ($step) {
    //                     return [
    //                         'stage' => $step->workflowStep?->name ?? 'N/A',
    //                         'role' => $step->role?->name ?? 'N/A',
    //                         'assigned_user' => $step->assignedUser?->name ?? '—',
    //                         'status' => $step->status,
    //                         'date' => $step->updated_at?->format('Y-m-d') ?? '-',
    //                     ];
    //                 })
    //             );
    //             $finalStatus = $req->getFinalStatus();

    //             $currentStage = match ($finalStatus['final_status']) {
    //                 'withdraw' => 'Withdrawn',
    //                 'approved' => 'Completed',
    //                 'rejected' => 'Rejected',
    //                 default => $finalStatus['pending_by'],
    //             };

    //             return [
    //                 'id' => $req->id,
    //                 'request_id' => $req->request_id,
    //                 'amount' => $req->amount,
    //                 'priority' => $req->priority,
    //                 'description' => $req->description,
    //                 'status' => $req->status,

    //                 'final_status' => $finalStatus['final_status'],
    //                 'pending_by' => $finalStatus['pending_by'], // backward compatible
    //                 'current_stage' => $currentStage,               // NEW (use this in UI)

    //                 'created_at' => $req->created_at?->format('Y-m-d H:i:s'),

    //                 'category' => [
    //                     'id' => $req->category,
    //                     'name' => $req->categoryData?->name,
    //                 ],

    //                 'entity' => [
    //                     'id' => $req->entiti,
    //                     'name' => $req->entityData?->name,
    //                 ],

    //                 'requested_by' => [
    //                     'id' => $req->user,
    //                     'name' => $req->userData?->name,
    //                 ],

    //                 'request_type' => [
    //                     'id' => $req->request_type,
    //                     'name' => $req->requestTypeData?->name,
    //                 ],

    //                 'department' => [
    //                     'id' => $req->department,
    //                     'name' => $req->departmentData?->name,
    //                 ],

    //                 'supplier' => [
    //                     'id' => $req->supplier_id,
    //                     'name' => $req->supplierData?->name,
    //                 ],

    //                 'workflow_history' => $approvalTimeline->values(),

    //                 'documents' => $req->documents->map(function ($doc) {
    //                     return [
    //                         'document_id' => $doc->document_id,
    //                         'document' => last(explode('_', $doc->document)),
    //                     ];
    //                 }),
    //             ];
    //         });

    //         return response()->json([
    //             'status' => 'success',
    //             'counts' => $counts,
    //             'total_amount' => [
    //                 'user_total_amount' => $userTotalAmount,
    //                 'entity_total_amount' => $entityTotalAmount,
    //                 'admin_total_amount' => $adminTotalAmount,
    //             ],
    //             'data' => $data,
    //         ]);

    //     } catch (\Exception $e) {

    //         Log::error('Request index failed: '.$e->getMessage());

    //         return response()->json([
    //             'status' => 'error',
    //             'message' => 'Failed to fetch requests',
    //             'error' => $e->getMessage(),
    //         ], 500);
    //     }
    // }
}
