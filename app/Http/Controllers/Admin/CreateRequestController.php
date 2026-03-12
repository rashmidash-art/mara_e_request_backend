<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BudgetCode;
use App\Models\DeliveryOrerDetails;
use App\Models\Department;
use App\Models\Document;
use App\Models\Entiti;
use App\Models\PaymentDetails;
use App\Models\PoUploadDetalils;
use App\Models\Request as ModelsRequest;
use App\Models\RequestDetailsDocuments;
use App\Models\RequestDocument;
use App\Models\RequestWorkflowDetails;
use App\Models\SupplierRating;
use App\Models\User;
use App\Models\WorkFlow;
use App\Models\WorkflowRoleAssign;
use App\Models\WorkflowStep;
use App\Services\NotificationService;
use Barryvdh\DomPDF\Facade\Pdf;
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
                'userData:id,name,designation',
                'requestTypeData:id,name',
                'departmentData:id,name',
                'supplierData:id,name',
                'budgetCode:id,budget_code',
                'documents:id,request_id,document_id,document',
                'currentWorkflowRole' => function ($q) {
                    $q->select('id', 'request_id', 'workflow_role_id', 'assigned_user_id', 'status', 'workflow_step_id');
                },
                'currentWorkflowRole.role:id,name',
                'currentWorkflowRole.assignedUser:id,name',
                'currentWorkflowRole.workflowStep:id,name',
                // Add these
                'workflowHistory' => function ($q) {
                    $q->with(['role', 'assignedUser', 'workflowStep'])->orderBy('id', 'asc');
                },
                'requestDetailsDocuments',
                'supplierRating',
            ])->orderByDesc('id');

            if ($isSuperAdmin) {
                $requests = $query->get();
            } elseif ($isEntityLogin) {
                $requests = $query->where('entiti', $auth->id)->get();
            } else {
                $userId = $auth->id;
                $userEntityId = $auth->entiti_id;

                $requests = $query
                    ->where('entiti', $userEntityId)
                    ->whereHas('workflowDetails', function ($q) use ($userId) {
                        $q->where('assigned_user_id', $userId)
                            ->where('status', 'pending')
                            ->where('is_sendback', 0)
                            ->whereRaw('workflow_step_id = (
                    SELECT workflow_step_id
                    FROM request_workflow_details rwd
                    WHERE rwd.request_id = request_workflow_details.request_id
                      AND rwd.status = "pending"
                      AND rwd.is_sendback = 0
                    ORDER BY workflow_step_id ASC
                    LIMIT 1
                )');
                    })
                    ->get();
            }

            $data = $requests->map(function ($req) {

                $workflow = $req->currentWorkflowRole;
                $finalStatus = $req->getFinalStatus();

                $currentStage = match ($finalStatus['final_status']) {
                    'Withdrawn' => 'Withdrawn',
                    'Rejected' => 'Rejected',
                    'Closed' => 'Completed',
                    default => $finalStatus['pending_by'],
                };

                //  Build workflow timeline (same as requestDetailsAll)
                $workflowTimeline = $req->workflowHistory
                    ->groupBy('workflow_step_id')
                    ->map(function ($steps) {
                        $first = $steps->first();
                        $lastAction = $steps->whereIn('status', ['approved', 'rejected'])
                            ->sortByDesc('updated_at')
                            ->first();

                        return [
                            'stage' => $first->workflowStep?->name ?? 'N/A',
                            'role' => $steps->pluck('role.name')->unique()->join(', '),
                            'assigned_user' => $lastAction?->assignedUser?->name,
                            'status' => $lastAction?->status ?? 'pending',
                            'date' => $lastAction?->updated_at?->format('Y-m-d'),
                        ];
                    })
                    ->values()
                    ->toArray();

                //  Build status timeline (same as requestDetailsAll)
                $statusTimeline = [[
                    'stage' => 'Submitted',
                    'status' => 'submitted',
                    'actor_name' => $req->userData?->name ?? 'Requester',
                    'date' => $req->created_at?->format('Y-m-d'),
                ]];

                if ($req->status === 'withdraw') {
                    $statusTimeline[] = [
                        'stage' => 'Withdrawn',
                        'status' => 'withdraw',
                        'actor_name' => $req->userData?->name ?? 'Requester',
                        'date' => $req->updated_at?->format('Y-m-d'),
                    ];
                } else {
                    $statusTimeline = array_merge($statusTimeline, $workflowTimeline);

                    $doc = $req->requestDetailsDocuments;

                    if ($doc?->is_po_created) {
                        $statusTimeline[] = [
                            'stage' => 'PO Created',
                            'status' => 'po created',
                            'actor_name' => $req->userData?->name,
                            'date' => $doc->po_date,
                        ];
                    }

                    if ($doc?->is_delivery_completed) {
                        $statusTimeline[] = [
                            'stage' => 'Delivery Completed',
                            'status' => 'delivery completed',
                            'actor_name' => $req->userData?->name,
                            'date' => $doc->delivery_completed_date,
                        ];
                    }

                    if ($doc?->is_payment_completed) {
                        $statusTimeline[] = [
                            'stage' => 'Payment Completed',
                            'status' => 'payment completed',
                            'actor_name' => $req->userData?->name,
                            'date' => $doc->payment_completed_date,
                        ];
                    }

                    if ($req->supplierRating) {
                        $statusTimeline[] = [
                            'stage' => 'Supplier Rated',
                            'status' => 'supplier rated',
                            'actor_name' => $req->userData?->name,
                            'date' => $req->supplierRating->created_at?->format('Y-m-d'),
                        ];
                    }

                    if ($req->status === 'closed') {
                        $statusTimeline[] = [
                            'stage' => 'Closed',
                            'status' => 'closed',
                            'actor_name' => 'System',
                            'date' => $req->updated_at?->format('Y-m-d'),
                        ];
                    }
                }

                return [
                    'id' => $req->id,
                    'request_id' => $req->request_id,
                    'amount' => $req->amount,
                    'priority' => $req->priority,
                    'description' => $req->description,
                    'business_justification' => $req->business_justification,
                    'status' => $req->status,
                    'created_at' => $req->created_at?->format('Y-m-d H:i:s'),
                    'updated_at' => $req->updated_at?->format('Y-m-d H:i:s'),
                    'final_status' => $finalStatus['final_status'],
                    'pending_by' => $finalStatus['pending_by'],
                    'current_stage' => $currentStage,

                    'user' => [
                        'id' => $req->user,
                        'name' => $req->userData?->name,
                        'designation' => $req->userData?->designation,
                    ],
                    'category' => ['id' => $req->category,    'name' => $req->categoryData?->name],
                    'entity' => ['id' => $req->entiti,      'name' => $req->entityData?->name],
                    'department' => ['id' => $req->department,  'name' => $req->departmentData?->name],
                    'budget_code' => ['id' => $req->budget_code, 'name' => $req->budgetCode?->budget_code],
                    'requested_by' => ['id' => $req->user,        'name' => $req->userData?->name],
                    'request_type' => ['id' => $req->request_type, 'name' => $req->requestTypeData?->name],
                    'supplier' => ['id' => $req->supplier_id, 'name' => $req->supplierData?->name],

                    'workflow' => [
                        'step' => $workflow?->workflowStep?->name,
                        'role' => $workflow?->role?->name,
                        'assigned_user' => $workflow?->assignedUser?->name,
                        'status' => $workflow?->status,
                    ],

                    //  Now included
                    'workflow_history' => $workflowTimeline,
                    'status_timeline' => $statusTimeline,

                    'documents' => $req->documents->map(function ($doc) {
                        return [
                            'document_id' => $doc->document_id,
                            'document' => last(explode('_', $doc->document)),
                            'url' => asset('storage/requestdocuments/'.$doc->document),
                        ];
                    }),
                ];
            });

            return response()->json(['status' => 'success', 'data' => $data]);

        } catch (\Exception $e) {
            Log::error('Request index failed: '.$e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch requests',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // public function store(Request $request)
    // {
    //     try {

    //         // ------------------------- VALIDATION ------------------------- //
    //         $validated = $request->validate([
    //             'entiti' => 'nullable|integer',
    //             'user' => 'nullable|integer',
    //             'request_type' => 'nullable|integer',
    //             'category' => 'nullable|integer',
    //             'department' => 'nullable|integer',
    //             'amount' => 'nullable|string',
    //             'description' => 'nullable|string',
    //             'supplier_id' => 'nullable|integer',
    //             'expected_date' => 'nullable|string',
    //             'priority' => 'nullable|string',
    //             'behalf_of' => 'nullable|in:0,1',
    //             'behalf_of_department' => 'required_if:behalf_of,1',
    //             'business_justification' => 'nullable|string',
    //             'status' => 'nullable|in:submitted,draft,deleted,withdraw',
    //             'attachments' => 'nullable|array',
    //             'attachments.*.document_id' => 'nullable|integer',
    //             'attachments.*.file' => 'required|file|max:10240',
    //             'budget_code' => 'nullable|exists:budget_codes,id',
    //             'behalf_of_buget_code' => 'nullable|exists:budget_codes,id',

    //         ]);

    //         // ------------------------- CREATE REQUEST ID ------------------------- //
    //         $year = date('Y');
    //         $last = ModelsRequest::whereYear('created_at', $year)->orderBy('id', 'desc')->first();
    //         $nextNumber = $last ? $last->id + 1 : 1;
    //         $request_no = "REQ-{$year}-".str_pad($nextNumber, 3, '0', STR_PAD_LEFT);

    //         // ------------------------- INSERT REQUEST MASTER ------------------------- //
    //         $req = ModelsRequest::create([
    //             'request_id' => $request_no,
    //             'entiti' => $request->entiti,
    //             'user' => $request->user,
    //             'request_type' => $request->request_type,
    //             'category' => $request->category,
    //             'department' => $request->department,
    //             'budget_code' => $request->budget_code,
    //             'amount' => $request->amount,
    //             'description' => $request->description,
    //             'supplier_id' => $request->supplier_id,
    //             'expected_date' => $request->expected_date,
    //             'priority' => $request->priority,
    //             'behalf_of' => $request->behalf_of,
    //             'behalf_of_department' => $request->behalf_of_department,
    //             'behalf_of_buget_code' => $request->behalf_of_buget_code,
    //             'business_justification' => $request->business_justification,
    //             'status' => $request->status ?? ModelsRequest::DRAFT,
    //         ]);

    //         // ------------------------- ATTACHMENTS ------------------------- //
    //         if (! empty($request->attachments)) {
    //             foreach ($request->attachments as $index => $doc) {

    //                 $file = $request->file("attachments.$index.file");
    //                 if (! $file) {
    //                     continue;
    //                 }

    //                 $departmentName = Department::find($req->department)->name ?? 'unknown';
    //                 $departmentName = str_replace(' ', '_', strtolower($departmentName));

    //                 $newFileName = $req->request_id.'_'.$req->entiti.'_'.$departmentName.'_'.$file->getClientOriginalName();
    //                 $file->storeAs('requestdocuments', $newFileName, 'public');

    //                 RequestDocument::create([
    //                     'request_id' => $req->request_id,
    //                     'document_id' => $doc['document_id'] ?? null,
    //                     'document' => $newFileName,
    //                 ]);
    //             }
    //         }

    //         // ------------------------- DRAFT: SKIP WORKFLOW ------------------------- //
    //         if ($req->status === 'draft') {
    //             return response()->json([
    //                 'status' => 'success',
    //                 'message' => 'Draft saved successfully',
    //                 'data' => $req,
    //             ], 201);
    //         }

    //         // ------------------------- FETCH WORKFLOW ------------------------- //
    //         $workflow = WorkFlow::where('entity_id', $req->entiti)
    //             ->where('categori_id', $req->category)
    //             ->where('request_type_id', $req->request_type)
    //             ->orderBy('id', 'desc')
    //             ->first();

    //         if (! $workflow) {
    //             return response()->json([
    //                 'status' => 'error',
    //                 'message' => 'Workflow not configured',
    //             ], 422);
    //         }

    //         $steps = WorkflowStep::where('workflow_id', $workflow->id)
    //             ->orderBy('order_id')
    //             ->get();

    //         foreach ($steps as $stepIndex => $step) {

    //             $roleAssigns = WorkflowRoleAssign::where('workflow_id', $workflow->id)
    //                 ->where('step_id', $step->id)
    //                 ->get();

    //             foreach ($roleAssigns as $roleAssign) {

    //                 $users = collect();

    //                 if ($roleAssign->specific_user == 1 && $roleAssign->user_id) {
    //                     $userIds = json_decode($roleAssign->user_id, true);
    //                     $userIds = is_array($userIds) ? $userIds : [$roleAssign->user_id];

    //                     $users = User::whereIn('id', $userIds)->get();
    //                 } else {
    //                     $users = User::whereHas('roles', function ($q) use ($roleAssign) {
    //                         $q->where('roles.id', $roleAssign->role_id);
    //                     })->get();
    //                 }

    //                 if ($users->isEmpty()) {
    //                     Log::warning('Workflow skipped: no users', [
    //                         'workflow_id' => $workflow->id,
    //                         'step_id' => $step->id,
    //                         'role_id' => $roleAssign->role_id,
    //                     ]);

    //                     continue;
    //                 }

    //                 if (strtolower($roleAssign->approval_logic) === 'and') {
    //                     foreach ($users as $user) {
    //                         RequestWorkflowDetails::create([
    //                             'request_id' => $req->request_id,
    //                             'workflow_id' => $workflow->id,
    //                             'workflow_step_id' => $step->id,
    //                             'workflow_role_id' => $roleAssign->role_id,
    //                             'assigned_user_id' => $user->id,
    //                             'status' => 'pending',
    //                             'approval_logic' => strtolower($roleAssign->approval_logic),
    //                             'is_sendback' => 0,
    //                         ]);
    //                         // ----------------- CREATE NOTIFICATION -----------------
    //                         NotificationService::send(
    //                             $user->id,
    //                             'New Request Assigned',
    //                             "You have been assigned a new request {$req->request_id} to approve.",
    //                             'request_assigned',
    //                             $req->request_id,
    //                             'request',
    //                             'Workflow assignment'
    //                         );
    //                     }
    //                 } else {
    //                     $assignedUser = $users->first();
    //                     RequestWorkflowDetails::create([
    //                         'request_id' => $req->request_id,
    //                         'workflow_id' => $workflow->id,
    //                         'workflow_step_id' => $step->id,
    //                         'workflow_role_id' => $roleAssign->role_id,
    //                         'assigned_user_id' => $users->first()->id,
    //                         'status' => 'pending',
    //                         'approval_logic' => strtolower($roleAssign->approval_logic),
    //                         'is_sendback' => 0,
    //                     ]);

    //                     // ----------------- CREATE NOTIFICATION -----------------
    //                     NotificationService::send(
    //                         $assignedUser->id,
    //                         'New Request Assigned',
    //                         "Request {$req->request_id} has been assigned to you for approval.",
    //                         'workflow_assigned',
    //                         $req->request_id,
    //                         'request',
    //                         'Assigned via workflow step '.$step->order_id
    //                     );
    //                 }
    //             }
    //         }

    //         return response()->json([
    //             'status' => 'success',
    //             'message' => 'Request created successfully',
    //             'data' => $req,
    //         ], 201);

    //     } catch (\Exception $e) {

    //         Log::error('Request Store Error', ['error' => $e->getMessage()]);

    //         return response()->json([
    //             'status' => 'error',
    //             'message' => 'Something went wrong',
    //             'error' => $e->getMessage(),
    //         ], 500);
    //     }
    // }

    /** Update request */
    // public function update(Request $request, $id)
    // {
    //     $req = ModelsRequest::find($id);

    //     if (! $req) {
    //         return response()->json([
    //             'status' => 'error',
    //             'message' => 'Request not found',
    //         ], 404);
    //     }

    //     /**
    //      * ===============================
    //      * RECALL REQUEST
    //      * ===============================
    //      */
    //     if ($request->status === 'draft') {

    //         if (! in_array($req->status, ['submitted', 'pending', 'in approval'])) {
    //             return response()->json([
    //                 'status' => 'error',
    //                 'message' => 'Only submitted or pending requests can be recalled',
    //             ], 422);
    //         }

    //         DB::transaction(function () use ($req) {

    //             $req->update(['status' => 'draft']);

    //             // Cancel pending workflow approvals
    //             RequestWorkflowDetails::where('request_id', $req->request_id)
    //                 ->where('status', 'pending')
    //                 ->update(['status' => 'cancelled']);
    //         });

    //         return response()->json([
    //             'status' => 'success',
    //             'message' => 'Request recalled to draft successfully',
    //         ]);
    //     }

    //     /**
    //      * ===============================
    //      * WITHDRAW REQUEST
    //      * ===============================
    //      */
    //     if ($request->status === 'withdraw') {

    //         if ($req->status !== 'submitted') {
    //             return response()->json([
    //                 'status' => 'error',
    //                 'message' => 'Only submitted requests can be withdrawn',
    //             ], 422);
    //         }

    //         DB::transaction(function () use ($req) {
    //             $req->update(['status' => 'withdraw']);

    //             RequestWorkflowDetails::where('request_id', $req->request_id)
    //                 ->where('status', 'pending')
    //                 ->update(['status' => 'withdraw']);
    //         });

    //         return response()->json([
    //             'status' => 'success',
    //             'message' => 'Request withdrawn successfully',
    //         ]);
    //     }

    //     /**
    //      * ===============================
    //      * VALIDATION (FULL)
    //      * ===============================
    //      */
    //     $validated = $request->validate([
    //         // Request master
    //         'entiti' => 'nullable|integer',
    //         'user' => 'nullable|integer',
    //         'request_type' => 'nullable|integer',
    //         'category' => 'nullable|integer',
    //         'department' => 'nullable|integer',
    //         'budget_code' => 'nullable|exists:budget_codes,id',
    //         'amount' => 'nullable|string',
    //         'description' => 'nullable|string',
    //         'supplier_id' => 'nullable|integer',
    //         'expected_date' => 'nullable|string',
    //         'priority' => 'nullable|string',
    //         'behalf_of' => 'nullable|in:0,1',
    //         'behalf_of_department' => 'required_if:behalf_of,1',
    //         'behalf_of_buget_code' => 'nullable|exists:budget_codes,id',
    //         'business_justification' => 'nullable|string',
    //         'status' => 'nullable|string',

    //         // Normal attachments
    //         'attachments' => 'nullable|array',
    //         'attachments.*.document_id' => 'required|integer',
    //         'attachments.*.file' => 'required|file|max:10240',

    //         // PO / Delivery / Payment
    //         'po_documents' => 'nullable|file|max:10240',
    //         'delivery_completed_documents' => 'nullable|file|max:10240',
    //         'payment_completed_documents' => 'nullable|file|max:10240',

    //         // Supplier rating
    //         'rating' => 'nullable|integer|min:1|max:5',
    //         'comment' => 'nullable|string|max:1000',
    //     ]);

    //     DB::transaction(function () use ($request, $req) {

    //         /**
    //          * ===============================
    //          * UPDATE REQUEST MASTER
    //          * ===============================
    //          */
    //         $oldStatus = $req->getOriginal('status');
    //         $req->update($request->only([
    //             'entiti',
    //             'user',
    //             'request_type',
    //             'category',
    //             'department',
    //             'budget_code',
    //             'amount',
    //             'description',
    //             'supplier_id',
    //             'expected_date',
    //             'priority',
    //             'behalf_of',
    //             'behalf_of_department',
    //             'behalf_of_buget_code',
    //             'business_justification',
    //             'status',
    //         ]));

    //         /**
    //          * ===============================
    //          * NORMAL ATTACHMENTS (LIKE STORE)
    //          * ===============================
    //          */
    //         if (! empty($request->attachments)) {
    //             foreach ($request->attachments as $index => $doc) {

    //                 $file = $request->file("attachments.$index.file");
    //                 if (! $file) {
    //                     continue;
    //                 }

    //                 $departmentName = Department::find($req->department)->name ?? 'unknown';
    //                 $departmentName = str_replace(' ', '_', strtolower($departmentName));

    //                 $fileName = $req->request_id.'_'.$req->entiti.'_'.$departmentName.'_'.$file->getClientOriginalName();
    //                 $file->storeAs('requestdocuments', $fileName, 'public');

    //                 RequestDocument::create([
    //                     'request_id' => $req->request_id,
    //                     'document_id' => $doc['document_id'],
    //                     'document' => $fileName,
    //                 ]);
    //             }
    //         }

    //         /**
    //          * ===============================
    //          * REQUEST DETAIL DOCUMENTS
    //          * ===============================
    //          */
    //         $doc = RequestDetailsDocuments::firstOrCreate(
    //             ['request_id' => $req->request_id],
    //             ['request_details_id' => $req->id]
    //         );

    //         /**
    //          * ===============================
    //          * PO UPLOAD
    //          * ===============================
    //          */
    //         if ($request->hasFile('po_documents')) {

    //             if ($req->poDetails()->exists()) {
    //                 throw new \Exception('PO already created for this request');
    //             }

    //             $file = $request->file('po_documents');
    //             $name = 'po_'.$req->request_id.'_'.$file->getClientOriginalName();
    //             $file->storeAs('request_documents', $name, 'public');

    //             PoUploadDetalils::create([
    //                 'request_id' => $req->request_id,
    //                 'is_po_created' => 1,
    //                 'po_number' => $request->po_number,
    //                 'po_amount' => $request->po_amount,
    //                 'po_date' => $request->po_date,
    //                 'po_documents' => $name,
    //                 'status' => 'completed',
    //             ]);

    //             $req->refresh()->recalculateStatus();
    //         }

    //         /**
    //          * ===============================
    //          * DELIVERY (PO REQUIRED)
    //          * ===============================
    //          */
    //         if ($request->hasFile('delivery_documents')) {

    //             if (! $req->poDetails()->exists()) {
    //                 throw new \Exception('Upload PO before delivery');
    //             }

    //             $file = $request->file('delivery_documents');
    //             $name = 'delivery_'.$req->request_id.'_'.time().'_'.$file->getClientOriginalName();
    //             $file->storeAs('request_documents', $name, 'public');

    //             DeliveryOrerDetails::create([
    //                 'request_id' => $req->request_id,
    //                 'is_delivery_completed' => $request->is_delivery_completed == 1 ? 1 : 0,                    'delivery_number' => $request->delivery_number,
    //                 'delivery_date' => $request->delivery_date,
    //                 'delivery_quantity' => $request->delivery_quantity,
    //                 'delivery_documents' => $name,
    //                 'status' => 'completed',
    //             ]);

    //             $req->refresh()->recalculateStatus();
    //         }

    //         /**
    //          * =======================================
    //          * RESTORE WORKFLOW (Draft → Submitted)
    //          * =======================================
    //          */
    //         if ($oldStatus === 'draft' && $request->status === 'submitted') {

    //             RequestWorkflowDetails::where('request_id', $req->request_id)
    //                 ->where('status', 'cancelled')
    //                 ->update(['status' => 'pending']);
    //         }
    //         /**
    //          * ===============================
    //          * PAYMENT (DELIVERY REQUIRED)
    //          * ===============================
    //          */
    //         if ($request->hasFile('payment_documents')) {

    //             if (! $req->deliveries()->exists()) {
    //                 throw new \Exception('Complete delivery before payment');
    //             }

    //             $file = $request->file('payment_documents');
    //             $name = 'payment_'.$req->request_id.'_'.time().'_'.$file->getClientOriginalName();
    //             $file->storeAs('request_documents', $name, 'public');

    //             PaymentDetails::create([
    //                 'request_id' => $req->request_id,
    //                 'is_payment_completed' => $request->is_payment_completed == 1 ? 1 : 0,
    //                 'payment_number' => $request->payment_number,
    //                 'payment_amount' => $request->payment_amount,
    //                 'payment_date' => $request->payment_date,
    //                 'payment_documents' => $name,
    //                 'status' => 'completed',
    //             ]);

    //             $req->refresh()->recalculateStatus();
    //         }
    //         /**
    //          * ===============================
    //          * SUPPLIER RATING (PAYMENT REQUIRED)
    //          * ===============================
    //          */
    //         if ($request->filled('rating')) {

    //             if ($req->user !== Auth::id()) {
    //                 throw new \Exception('Only request owner can rate supplier');
    //             }

    //             if (! $req->payments()->exists()) {
    //                 throw new \Exception('Complete payment before rating');
    //             }

    //             if (! $req->supplier_id) {
    //                 throw new \Exception('No supplier linked to request');
    //             }

    //             if (SupplierRating::where('request_id', $req->request_id)->exists()) {
    //                 throw new \Exception('Supplier already rated');
    //             }

    //             SupplierRating::create([
    //                 'request_id' => $req->request_id,
    //                 'user_id' => Auth::id(),
    //                 'supplier_id' => $req->supplier_id,
    //                 'rating' => $request->rating,
    //                 'comment' => $request->comment,
    //                 'status' => 'completed',
    //             ]);

    //             $req->changeStatus(ModelsRequest::CLOSED);
    //         }
    //     });

    //     return response()->json([
    //         'status' => 'success',
    //         'message' => 'Request updated successfully',
    //     ]);
    // }

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
                'attachments.*.document_id' => 'nullable|integer',
                'attachments.*.file' => 'required|file|max:10240',
                'budget_code' => 'nullable|exists:budget_codes,id',
                'behalf_of_buget_code' => 'nullable|exists:budget_codes,id',
            ]);

            $status = $request->status ?? ModelsRequest::DRAFT;

            // ------------------------- WORKFLOW CHECK ------------------------- //
            $workflow = null;
            if ($status !== 'draft') {
                $workflow = WorkFlow::where('entity_id', $request->entiti)
                    ->where('categori_id', $request->category)
                    ->where('request_type_id', $request->request_type)
                    ->orderBy('id', 'desc')
                    ->first();

                if (! $workflow) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Workflow not configured for selected entity, category, and request type',
                    ], 422);
                }
            }

            // ------------------------- CREATE REQUEST WITH TRANSACTION ------------------------- //
            DB::transaction(function () use ($request, $workflow, $status, &$req) {
                // Generate Request ID
                $year = date('Y');
                $last = ModelsRequest::whereYear('created_at', $year)->orderBy('id', 'desc')->first();
                $nextNumber = $last ? $last->id + 1 : 1;
                $request_no = "REQ-{$year}-".str_pad($nextNumber, 3, '0', STR_PAD_LEFT);

                // Create request
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
                    'behalf_of_buget_code' => $request->behalf_of_buget_code,
                    'business_justification' => $request->business_justification,
                    'status' => $status,
                ]);

                // Handle attachments
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
                            'document_id' => $doc['document_id'] ?? null,
                            'document' => $newFileName,
                        ]);
                    }
                }

                // Skip workflow assignment if draft
                if ($status === 'draft') {
                    return;
                }

                // Workflow assignment
                // Workflow assignment - MODIFIED FOR STEP-BY-STEP NOTIFICATIONS
                // Workflow assignment - SKIP CREATION STEP
                $steps = WorkflowStep::where('workflow_id', $workflow->id)
                    ->orderBy('order_id')
                    ->get();
                $approvalSteps = $steps->filter(function ($step) {
                    return $step->status !== 'Yes';
                })->values();
                if ($approvalSteps->isEmpty()) {
                    $approvalSteps = $steps->slice(1)->values();
                }
                foreach ($approvalSteps as $index => $step) {
                    $isFirstApprovalStep = ($index === 0);
                    $roleAssigns = WorkflowRoleAssign::where('workflow_id', $workflow->id)
                        ->where('step_id', $step->id)
                        ->get();
                    foreach ($roleAssigns as $roleAssign) {
                        $users = collect();
                        if ($roleAssign->specific_user == 1 && $roleAssign->user_id) {
                            $userIds = json_decode($roleAssign->user_id, true);
                            $userIds = is_array($userIds) ? $userIds : [$roleAssign->user_id];
                            $users = User::whereIn('id', $userIds)->get();
                        } else {
                            $users = User::whereHas('roles', function ($q) use ($roleAssign) {
                                $q->where('roles.id', $roleAssign->role_id);
                            })->get();
                        }
                        if ($users->isEmpty()) {
                            continue;
                        }
                        if (strtolower($roleAssign->approval_logic) === 'and') {
                            foreach ($users as $user) {
                                RequestWorkflowDetails::create([
                                    'request_id' => $req->request_id,
                                    'workflow_id' => $workflow->id,
                                    'workflow_step_id' => $step->id,
                                    'workflow_role_id' => $roleAssign->role_id,
                                    'assigned_user_id' => $user->id,
                                    'status' => $isFirstApprovalStep ? 'pending' : 'waiting',
                                    'approval_logic' => strtolower($roleAssign->approval_logic),
                                    'is_sendback' => 0,
                                ]);

                                // ONLY send notifications for the FIRST APPROVAL step
                                if ($isFirstApprovalStep) {
                                    NotificationService::send(
                                        $user->id,
                                        'New Request Assigned',
                                        "You have been assigned a new request {$req->request_id} to approve.",
                                        'request_assigned',
                                        $req->request_id,
                                        'request',
                                        'Workflow assignment'
                                    );
                                }
                            }
                        } else {
                            $assignedUser = $users->first();
                            RequestWorkflowDetails::create([
                                'request_id' => $req->request_id,
                                'workflow_id' => $workflow->id,
                                'workflow_step_id' => $step->id,
                                'workflow_role_id' => $roleAssign->role_id,
                                'assigned_user_id' => $assignedUser->id,
                                'status' => $isFirstApprovalStep ? 'pending' : 'waiting',
                                'approval_logic' => strtolower($roleAssign->approval_logic),
                                'is_sendback' => 0,
                            ]);

                            // ONLY send notifications for the FIRST APPROVAL step
                            if ($isFirstApprovalStep) {
                                NotificationService::send(
                                    $assignedUser->id,
                                    'New Request Assigned',
                                    "Request {$req->request_id} has been assigned to you for approval.",
                                    'workflow_assigned',
                                    $req->request_id,
                                    'request',
                                    'Assigned via workflow step '.$step->order_id
                                );
                            }
                        }
                    }
                }
            });

            // ------------------------- RESPONSE ------------------------- //
            return response()->json([
                'status' => 'success',
                'message' => $status === 'draft' ? 'Draft saved successfully' : 'Request created successfully',
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

    public function update(Request $request, $id)
    {
        $req = ModelsRequest::find($id);

        if (! $req) {
            return response()->json([
                'status' => 'error',
                'message' => 'Request not found',
            ], 404);
        }

        DB::transaction(function () use ($request, $req) {

            $oldStatus = $req->getOriginal('status');

            // -------------------------- RECALL TO DRAFT -------------------------- //
            if ($request->status === 'draft') {
                if (! in_array($req->status, ['submitted', 'pending', 'in_approval'])) {
                    throw new \Exception('Only submitted or pending requests can be recalled');
                }

                $req->update(['status' => 'draft']);

                // Delete workflow details
                RequestWorkflowDetails::where('request_id', $req->request_id)->delete();

                // DON'T delete documents here - keep them for when user edits

                return response()->json([
                    'status' => 'success',
                    'message' => 'Request recalled to draft successfully',
                ]);
            }

            // -------------------------- WITHDRAW REQUEST -------------------------- //
            if ($request->status === 'withdraw') {
                if ($req->status !== 'submitted') {
                    throw new \Exception('Only submitted requests can be withdrawn');
                }

                $req->update(['status' => 'withdraw']);

                RequestWorkflowDetails::where('request_id', $req->request_id)
                    ->where('status', 'pending')
                    ->update(['status' => 'withdraw']);

                return response()->json([
                    'status' => 'success',
                    'message' => 'Request withdrawn successfully',
                ]);
            }

            // -------------------------- UPDATE REQUEST MASTER -------------------------- //
            $validated = $request->validate([
                'entiti' => 'nullable|integer',
                'user' => 'nullable|integer',
                'request_type' => 'nullable|integer',
                'category' => 'nullable|integer',
                'department' => 'nullable|integer',
                'budget_code' => 'nullable|exists:budget_codes,id',
                'amount' => 'nullable|string',
                'description' => 'nullable|string',
                'supplier_id' => 'nullable|integer',
                'expected_date' => 'nullable|string',
                'priority' => 'nullable|string',
                'behalf_of' => 'nullable|in:0,1',
                'behalf_of_department' => 'required_if:behalf_of,1',
                'behalf_of_buget_code' => 'nullable|exists:budget_codes,id',
                'business_justification' => 'nullable|string',
                'status' => 'nullable|string',
                'attachments' => 'nullable|array',
                'attachments.*.document_id' => 'nullable|integer',
                'attachments.*.file' => 'required|file|max:10240',
                // Add this to handle removed documents
                'removed_documents' => 'nullable|array',
                'removed_documents.*' => 'integer|exists:request_documents,id',
            ]);

            if ($request->has('removed_documents') && is_array($request->removed_documents)) {
                foreach ($request->removed_documents as $documentId) {
                    $document = RequestDocument::find($documentId);
                    if ($document) {
                        // Delete the file from storage
                        $filePath = storage_path('app/public/requestdocuments/'.$document->document);
                        if (file_exists($filePath)) {
                            unlink($filePath);
                        }
                        // Delete the record
                        $document->delete();
                    }
                }
            }

            $req->update($request->only([
                'entiti', 'user', 'request_type', 'category', 'department',
                'budget_code', 'amount', 'description', 'supplier_id', 'expected_date',
                'priority', 'behalf_of', 'behalf_of_department', 'behalf_of_buget_code',
                'business_justification', 'status',
            ]));

            // -------------------------- ATTACHMENTS -------------------------- //
            // -------------------------- ATTACHMENTS -------------------------- //
            if (! empty($request->attachments)) {
                foreach ($request->attachments as $index => $doc) {
                    $file = $request->file("attachments.$index.file");
                    if (! $file) {
                        continue;
                    }

                    $departmentName = Department::find($req->department)->name ?? 'unknown';
                    $departmentName = str_replace(' ', '_', strtolower($departmentName));

                    $fileName = $req->request_id.'_'.$req->entiti.'_'.$departmentName.'_'.$file->getClientOriginalName();
                    $file->storeAs('requestdocuments', $fileName, 'public');

                    RequestDocument::create([
                        'request_id' => $req->request_id,
                        'document_id' => $doc['document_id'] ?? null,
                        'document' => $fileName,
                    ]);
                }
            }
            // -------------------------- DRAFT → SUBMITTED: RECREATE WORKFLOW -------------------------- //
            if ($oldStatus === 'draft' && $request->status === 'submitted') {

                // Delete old workflow details if any
                RequestWorkflowDetails::where('request_id', $req->request_id)
                    ->delete();

                // Fetch workflow
                $workflow = WorkFlow::where('entity_id', $req->entiti)
                    ->where('categori_id', $req->category)
                    ->where('request_type_id', $req->request_type)
                    ->orderBy('id', 'desc')
                    ->first();

                if ($workflow) {
                    $steps = WorkflowStep::where('workflow_id', $workflow->id)
                        ->orderBy('order_id')
                        ->get();

                    // Filter out creation steps
                    $approvalSteps = $steps->filter(function ($step) {
                        return $step->status !== 'Yes';
                    })->values();

                    if ($approvalSteps->isEmpty()) {
                        $approvalSteps = $steps->slice(1)->values();
                    }

                    foreach ($approvalSteps as $index => $step) {
                        $isFirstApprovalStep = ($index === 0);
                        $roleAssigns = WorkflowRoleAssign::where('workflow_id', $workflow->id)
                            ->where('step_id', $step->id)
                            ->get();

                        foreach ($roleAssigns as $roleAssign) {
                            $users = collect();
                            if ($roleAssign->specific_user == 1 && $roleAssign->user_id) {
                                $userIds = json_decode($roleAssign->user_id, true);
                                $userIds = is_array($userIds) ? $userIds : [$roleAssign->user_id];
                                $users = User::whereIn('id', $userIds)->get();
                            } else {
                                $users = User::whereHas('roles', function ($q) use ($roleAssign) {
                                    $q->where('roles.id', $roleAssign->role_id);
                                })->get();
                            }

                            if ($users->isEmpty()) {
                                continue;
                            }

                            if (strtolower($roleAssign->approval_logic) === 'and') {
                                foreach ($users as $user) {
                                    RequestWorkflowDetails::create([
                                        'request_id' => $req->request_id,
                                        'workflow_id' => $workflow->id,
                                        'workflow_step_id' => $step->id,
                                        'workflow_role_id' => $roleAssign->role_id,
                                        'assigned_user_id' => $user->id,
                                        'status' => $isFirstApprovalStep ? 'pending' : 'waiting',
                                        'approval_logic' => strtolower($roleAssign->approval_logic),
                                        'is_sendback' => 0,
                                    ]);

                                    if ($isFirstApprovalStep) {
                                        NotificationService::send(
                                            $user->id,
                                            'New Request Assigned',
                                            "You have been assigned a new request {$req->request_id} to approve.",
                                            'request_assigned',
                                            $req->request_id,
                                            'request',
                                            'Workflow assignment'
                                        );
                                    }
                                }
                            } else {
                                $assignedUser = $users->first();
                                RequestWorkflowDetails::create([
                                    'request_id' => $req->request_id,
                                    'workflow_id' => $workflow->id,
                                    'workflow_step_id' => $step->id,
                                    'workflow_role_id' => $roleAssign->role_id,
                                    'assigned_user_id' => $assignedUser->id,
                                    'status' => $isFirstApprovalStep ? 'pending' : 'waiting',
                                    'approval_logic' => strtolower($roleAssign->approval_logic),
                                    'is_sendback' => 0,
                                ]);

                                if ($isFirstApprovalStep) {
                                    NotificationService::send(
                                        $assignedUser->id,
                                        'New Request Assigned',
                                        "Request {$req->request_id} has been assigned to you for approval.",
                                        'workflow_assigned',
                                        $req->request_id,
                                        'request',
                                        'Assigned via workflow step '.$step->order_id
                                    );
                                }
                            }
                        }
                    }
                }
            }

            // -------------------------- PO / Delivery / Payment -------------------------- //
            if ($request->hasFile('po_documents')) {
                $file = $request->file('po_documents');
                $name = 'po_'.$req->request_id.'_'.time().'_'.$file->getClientOriginalName();
                $file->storeAs('request_documents', $name, 'public');

                PoUploadDetalils::create([
                    'request_id' => $req->request_id,
                    'is_po_created' => 1,
                    'po_number' => $request->po_number,
                    'po_amount' => $request->po_amount,
                    'po_date' => $request->po_date,
                    'po_documents' => $name,
                    'status' => 'completed',
                ]);

                $req->refresh()->recalculateStatus();
            }

            // -------------------------- DELIVERY -------------------------- //

            if (
                $request->hasFile('delivery_documents') ||
                $request->filled('delivery_number') ||
                $request->is_delivery_completed == 1
            ) {

                if (! $req->poDetails()->exists()) {
                    throw new \Exception('Upload PO before delivery');
                }

                // CASE 1: Checkbox checked, no data
                if (
                    $request->is_delivery_completed == 1 &&
                    ! $request->filled('delivery_number') &&
                    ! $request->hasFile('delivery_documents')
                ) {

                    DeliveryOrerDetails::create([
                        'request_id' => $req->request_id,
                        'is_delivery_completed' => 1,
                        'delivery_number' => null,
                        'delivery_date' => null,
                        'delivery_quantity' => null,
                        'delivery_documents' => null,
                        'status' => 'completed',
                    ]);

                    $req->refresh()->recalculateStatus();

                    return;
                }

                // CASE 2: If delivery number entered → require all fields
                $request->validate([
                    'delivery_number' => 'required|string',
                    'delivery_date' => 'required|date',
                    'delivery_quantity' => 'required|numeric',
                    'delivery_documents' => 'required|file',
                ]);

                $file = $request->file('delivery_documents');
                $name = 'delivery_'.$req->request_id.'_'.time().'_'.$file->getClientOriginalName();
                $file->storeAs('request_documents', $name, 'public');

                DeliveryOrerDetails::create([
                    'request_id' => $req->request_id,
                    'is_delivery_completed' => $request->is_delivery_completed == 1 ? 1 : 0,
                    'delivery_number' => $request->delivery_number,
                    'delivery_date' => $request->delivery_date,
                    'delivery_quantity' => $request->delivery_quantity,
                    'delivery_documents' => $name,
                    'status' => 'completed',
                ]);

                $req->refresh()->recalculateStatus();
            }

            // -------------------------- PAYMENT -------------------------- //

            if (
                $request->hasFile('payment_documents') ||
                $request->filled('payment_number') ||
                $request->is_payment_completed == 1
            ) {

                if (! $req->deliveries()->exists()) {
                    throw new \Exception('Complete delivery before payment');
                }

                // CASE 1: Checkbox checked, no data
                if (
                    $request->is_payment_completed == 1 &&
                    ! $request->filled('payment_number') &&
                    ! $request->hasFile('payment_documents')
                ) {

                    PaymentDetails::create([
                        'request_id' => $req->request_id,
                        'is_payment_completed' => 1,
                        'payment_number' => null,
                        'payment_amount' => null,
                        'payment_date' => null,
                        'payment_documents' => null,
                        'status' => 'completed',
                    ]);

                    $req->refresh()->recalculateStatus();

                    return;
                }

                // CASE 2: If payment number entered → require all fields
                $request->validate([
                    'payment_number' => 'required|string',
                    'payment_amount' => 'required|numeric',
                    'payment_date' => 'required|date',
                    'payment_documents' => 'required|file',
                ]);

                $file = $request->file('payment_documents');
                $name = 'payment_'.$req->request_id.'_'.time().'_'.$file->getClientOriginalName();
                $file->storeAs('request_documents', $name, 'public');

                PaymentDetails::create([
                    'request_id' => $req->request_id,
                    'is_payment_completed' => $request->is_payment_completed == 1 ? 1 : 0,
                    'payment_number' => $request->payment_number,
                    'payment_amount' => $request->payment_amount,
                    'payment_date' => $request->payment_date,
                    'payment_documents' => $name,
                    'status' => 'completed',
                ]);

                $req->refresh()->recalculateStatus();
            }

            // if ($request->hasFile('delivery_documents')) {
            //     if (! $req->poDetails()->exists()) {
            //         throw new \Exception('Upload PO before delivery');
            //     }

            //     $file = $request->file('delivery_documents');
            //     $name = 'delivery_'.$req->request_id.'_'.time().'_'.$file->getClientOriginalName();
            //     $file->storeAs('request_documents', $name, 'public');

            //     DeliveryOrerDetails::create([
            //         'request_id' => $req->request_id,
            //         'is_delivery_completed' => $request->is_delivery_completed == 1 ? 1 : 0,
            //         'delivery_number' => $request->delivery_number,
            //         'delivery_date' => $request->delivery_date,
            //         'delivery_quantity' => $request->delivery_quantity,
            //         'delivery_documents' => $name,
            //         'status' => 'completed',
            //     ]);

            //     $req->refresh()->recalculateStatus();
            // }

            // if ($request->hasFile('payment_documents')) {
            //     if (! $req->deliveries()->exists()) {
            //         throw new \Exception('Complete delivery before payment');
            //     }

            //     $file = $request->file('payment_documents');
            //     $name = 'payment_'.$req->request_id.'_'.time().'_'.$file->getClientOriginalName();
            //     $file->storeAs('request_documents', $name, 'public');

            //     PaymentDetails::create([
            //         'request_id' => $req->request_id,
            //         'is_payment_completed' => $request->is_payment_completed == 1 ? 1 : 0,
            //         'payment_number' => $request->payment_number,
            //         'payment_amount' => $request->payment_amount,
            //         'payment_date' => $request->payment_date,
            //         'payment_documents' => $name,
            //         'status' => 'completed',
            //     ]);

            //     $req->refresh()->recalculateStatus();
            // }

            // -------------------------- SUPPLIER RATING -------------------------- //
            if ($request->filled('rating')) {
                if ($req->user !== Auth::id()) {
                    throw new \Exception('Only request owner can rate supplier');
                }
                if (! $req->payments()->exists()) {
                    throw new \Exception('Complete payment before rating');
                }
                if (! $req->supplier_id) {
                    throw new \Exception('No supplier linked to request');
                }
                if (SupplierRating::where('request_id', $req->request_id)->exists()) {
                    throw new \Exception('Supplier already rated');
                }

                SupplierRating::create([
                    'request_id' => $req->request_id,
                    'user_id' => Auth::id(),
                    'supplier_id' => $req->supplier_id,
                    'rating' => $request->rating,
                    'comment' => $request->comment,
                    'status' => 'completed',
                ]);

                $req->changeStatus(ModelsRequest::CLOSED);
            }
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Request updated successfully',
        ]);
    }

    /** Create a new request */
    public function show($id)
    {
        try {
            $req = ModelsRequest::with(['documents'])->find($id); // Eager load documents

            if (! $req) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Request not found',
                ], 404);
            }

            $documents = $req->documents->map(function ($doc) {
                return [
                    'document_id' => $doc->document_id,
                    'file_name' => $doc->document,
                    'url' => asset('storage/requestdocuments/'.$doc->document),
                    // Extract original filename from stored filename
                    'original_name' => $this->extractOriginalFilename($doc->document),
                ];
            });

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

    // Helper method to extract original filename
    private function extractOriginalFilename($storedFilename)
    {
        $parts = explode('_', $storedFilename);
        $originalParts = array_slice($parts, 3);

        return implode('_', $originalParts);
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
                'requestDetailsDocuments',
                'supplierRating',
            ]);

            if ($isSuperAdmin) {
                $requests = $baseQuery->orderByDesc('id')->get();
            } elseif ($isEntityLogin) {
                $requests = $baseQuery->where('entiti', $auth->id)->orderByDesc('id')->get();
            } else {
                $requests = $baseQuery->where('user', $userId)->orderByDesc('id')->get();
            }

            $data = $requests->map(function ($req) {

                /* ======================================================
                 * 1. Workflow timeline (deduplicated)
                 * ====================================================== */
                $workflowTimeline = $req->workflowHistory
                    ->groupBy('workflow_step_id')
                    ->map(function ($steps) {
                        $first = $steps->first();
                        $lastAction = $steps->whereIn('status', ['approved', 'rejected'])
                            ->sortByDesc('updated_at')
                            ->first();

                        return [
                            'stage' => $first->workflowStep?->name ?? 'N/A',
                            'role' => $steps->pluck('role.name')->unique()->join(', '),
                            'assigned_user' => $lastAction?->assignedUser?->name,
                            'status' => $lastAction?->status ?? 'pending',
                            'date' => $lastAction?->updated_at?->format('Y-m-d H:i'),
                        ];
                    })
                    ->values()
                    ->toArray();

                /* ======================================================
                 * 2. Unified Status Timeline
                 * ====================================================== */

                $statusTimeline = [];

                /*
                |--------------------------------------------------------------------------
                | 1️⃣ Submitted (only if not draft or withdraw)
                |--------------------------------------------------------------------------
                */
                if ($req->status !== ModelsRequest::DRAFT && $req->status !== ModelsRequest::WITHDRAW) {
                    $statusTimeline[] = [
                        'stage' => 'Request Submitted',
                        'status' => 'submitted',
                        'actor_name' => $req->userData?->name ?? 'Requester',
                        'date' => $req->created_at?->format('Y-m-d'),
                    ];
                }

                /*
                |--------------------------------------------------------------------------
                | 2️⃣ Draft
                |--------------------------------------------------------------------------
                */
                if ($req->status === ModelsRequest::DRAFT) {
                    $statusTimeline[] = [
                        'stage' => 'Request Converted to Draft',
                        'status' => 'draft',
                        'actor_name' => $req->userData?->name ?? 'Requester',
                        'date' => $req->updated_at?->format('Y-m-d'),
                    ];
                }

                /*
                |--------------------------------------------------------------------------
                | 3️⃣ Withdraw - REMOVED THE EARLY RETURN
                |--------------------------------------------------------------------------
                */
                if ($req->status === ModelsRequest::WITHDRAW) {
                    $statusTimeline[] = [
                        'stage' => 'Withdrawn',
                        'status' => 'withdraw',
                        'actor_name' => $req->userData?->name ?? 'Requester',
                        'date' => $req->updated_at?->format('Y-m-d'),
                    ];

                    // Don't return here - continue to build the rest of the response
                } else {
                    /*
                    |--------------------------------------------------------------------------
                    | 4️⃣ Workflow Steps (only if not withdrawn)
                    |--------------------------------------------------------------------------
                    */
                    foreach ($workflowTimeline as $step) {
                        $statusTimeline[] = $step;
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | 5️⃣ PO Created
                    |--------------------------------------------------------------------------
                    */
                    if ($req->poDetails) {
                        $statusTimeline[] = [
                            'stage' => 'PO Created',
                            'status' => 'po created',
                            'actor_name' => $req->poDetails->createdBy?->name ?? 'System',
                            'date' => \Carbon\Carbon::parse($req->poDetails->po_date)->format('Y-m-d'),
                        ];
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | 6️⃣ Delivery Completed
                    |--------------------------------------------------------------------------
                    */
                    $finalDelivery = $req->deliveries()
                        ->where('is_delivery_completed', 1)
                        ->first();

                    if ($finalDelivery) {
                        $statusTimeline[] = [
                            'stage' => 'Delivery Completed',
                            'status' => 'delivery completed',
                            'actor_name' => 'System',
                            'date' => \Carbon\Carbon::parse($finalDelivery->delivery_date)->format('Y-m-d'),
                        ];
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | 7️⃣ Payment Completed
                    |--------------------------------------------------------------------------
                    */
                    $finalPayment = $req->payments()
                        ->where('is_payment_completed', 1)
                        ->first();

                    if ($finalPayment) {
                        $statusTimeline[] = [
                            'stage' => 'Payment Completed',
                            'status' => 'payment completed',
                            'actor_name' => 'System',
                            'date' => \Carbon\Carbon::parse($finalPayment->payment_date)->format('Y-m-d'),
                        ];
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | 8️⃣ Supplier Rated
                    |--------------------------------------------------------------------------
                    */
                    if ($req->supplierRating) {
                        $statusTimeline[] = [
                            'stage' => 'Supplier Rated',
                            'status' => 'supplier rated',
                            'actor_name' => $req->supplierRating->user?->name ?? 'Requester',
                            'date' => $req->supplierRating->created_at?->format('Y-m-d'),
                        ];
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | 9️⃣ Closed
                    |--------------------------------------------------------------------------
                    */
                    if ($req->status === ModelsRequest::CLOSED) {
                        $statusTimeline[] = [
                            'stage' => 'Closed',
                            'status' => 'closed',
                            'actor_name' => 'System',
                            'date' => $req->updated_at?->format('Y-m-d'),
                        ];
                    }
                }

                $finalStatus = $req->getFinalStatus();

                /* ======================================================
                 * Department and Budget Code Logic
                 * ====================================================== */

                $isBehalf = $req->behalf_of === 1;

                // Pick correct department ID
                $departmentId = $isBehalf ? $req->behalf_of_department : $req->department;
                $budgetCodeId = $isBehalf ? $req->behalf_of_buget_code : $req->budget_code;

                // Fetch department
                $department = Department::find($departmentId);

                // Fetch full budget code details
                $budget = $budgetCodeId
                    ? BudgetCode::select('id', 'budget_code', 'budget_limit', 'description', 'status')
                        ->where('id', $budgetCodeId)
                        ->first()
                    : null;

                // Supplier
                $supplier = $req->supplier;

                // Expected Date (formatted Y-m-d)
                $expectedDate = $req->expected_date
                    ? \Carbon\Carbon::parse($req->expected_date)->format('Y-m-d')
                    : null;

                $rejectionRemark = $req->workflowDetails()
                    ->where('status', 'rejected')
                    ->latest()
                    ->value('remark');

                return [
                    'id' => $req->id,
                    'request_id' => $req->request_id,
                    'amount' => (float) $req->amount,
                    'priority' => $req->priority,
                    'description' => $req->description,
                    'business_justification' => $req->business_justification,
                    'remark' => $rejectionRemark,
                    'status' => $req->status,
                    'final_status' => $finalStatus['final_status'],
                    'pending_by' => $finalStatus['pending_by'],
                    'current_stage' => $req->status === 'withdraw'
                        ? 'Withdrawn'
                        : ($finalStatus['final_status'] === 'approved' ? 'Completed' : $finalStatus['pending_by']),
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
                        'id' => $department?->id,
                        'name' => $department?->name,
                        'budget_code' => $budget?->budget_code ?? $budget?->budget_limit,
                        'type' => $isBehalf ? 'behalf' : 'self',
                    ],
                    'supplier' => [
                        'id' => $req->supplier_id,
                        'name' => $req->supplierData?->name,
                    ],
                    'expected_date' => $expectedDate,
                    'workflow_history' => $workflowTimeline,
                    'status_timeline' => $statusTimeline,

                    'documents' => $req->documents->map(fn ($doc) => [
                        'document_id' => $doc->document_id,
                        'document' => last(explode('_', $doc->document)),
                        'url' => asset('storage/requestdocuments/'.$doc->document),
                    ]),

                    'po_amount' => $req->poDetails?->po_amount ?? 0,
                    'total_paid' => $req->payments()->sum('payment_amount') ?? 0,

                    'status_flags' => [
                        'is_po_created' => $req->poDetails()->exists() ? 1 : 0,
                        'is_delivery_completed' => $req->deliveries()
                            ->where('is_delivery_completed', 1)
                            ->exists() ? 1 : 0,
                        'is_payment_completed' => $req->payments()
                            ->where('is_payment_completed', 1)
                            ->exists() ? 1 : 0,
                    ],

                    /* ================= DOCUMENT DETAILS ================= */

                    'po_details' => $req->poDetails ? [
                        'number' => $req->poDetails->po_number,
                        'date' => $req->poDetails->po_date,
                        'amount' => $req->poDetails->po_amount,
                        'file' => $req->poDetails->po_documents
                            ? asset('storage/request_documents/'.$req->poDetails->po_documents)
                            : null,
                    ] : null,

                    'delivery_details' => $req->deliveries->map(function ($delivery) {
                        return [
                            'number' => $delivery->delivery_number,
                            'date' => $delivery->delivery_date,
                            'quantity' => $delivery->delivery_quantity,
                            'is_delivery_completed' => $delivery->is_delivery_completed,
                            'file' => $delivery->delivery_documents
                                ? asset('storage/request_documents/'.$delivery->delivery_documents)
                                : null,
                        ];
                    }),

                    'payment_details' => $req->payments->map(function ($payment) {
                        return [
                            'number' => $payment->payment_number,
                            'date' => $payment->payment_date,
                            'amount' => $payment->payment_amount,
                            'is_payment_completed' => $payment->is_payment_completed,
                            'file' => $payment->payment_documents
                                ? asset('storage/request_documents/'.$payment->payment_documents)
                                : null,
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
            ], 500);
        }
    }

    public function downloadRequestPdf($id)
    {
        try {
            $requestData = ModelsRequest::with([
                'userData:id,name',
                'categoryData:id,name',
                'departmentData:id,name',
                'supplierData:id,name',
                'entityData:id,name',
                'requestTypeData:id,name',
                'documents:id,request_id,document_id,document',

                'workflowHistory' => function ($q) {
                    $q->with([
                        'workflowStep:id,name',
                        'role:id,name',
                        'assignedUser:id,name',
                    ])->orderBy('id', 'asc');
                },

                'requestDetailsDocuments',
                'supplierRating',
                'poDetails',
                'deliveries',
                'payments',
            ])
                ->where(function ($q) use ($id) {
                    $q->where('id', $id)            // numeric DB id
                        ->orWhere('request_id', $id); // REQ-2026-071
                })
                ->firstOrFail();

            $workflowTimeline = $requestData->workflowHistory
                ->groupBy(fn ($wf) => $wf->workflowStep?->id)
                ->map(function ($stepGroup) {

                    $actionTaken = $stepGroup->whereIn('status', ['approved', 'rejected']);

                    if ($actionTaken->isNotEmpty()) {
                        return [
                            'step' => $stepGroup->first()?->workflowStep?->name,
                            'role' => $actionTaken->pluck('role.name')->unique()->implode(', '),
                            'assigned_user' => $actionTaken->pluck('assignedUser.name')->unique()->implode(', '),
                            'status' => $actionTaken->contains('status', 'approved')
                                ? 'approved'
                                : 'rejected',
                            'date' => optional($actionTaken->max('updated_at'))->format('Y-m-d'),
                            'remark' => $actionTaken->pluck('remark')->filter()->implode(' | '),
                        ];
                    }

                    return [
                        'step' => $stepGroup->first()?->workflowStep?->name ?? 'Approval Step',
                        'role' => $stepGroup->first()?->role?->name ?? 'N/A',
                        'assigned_user' => 'Pending',
                        'status' => 'pending',
                        'date' => '-',
                        'remark' => '',
                    ];
                })
                ->values()
                ->toArray();

            $lifecycleTimeline = [];

            $doc = $requestData->requestDetailsDocuments;

            if ($doc?->is_po_created) {
                $lifecycleTimeline[] = [
                    'label' => 'PO Created',
                    'date' => $doc->po_date,
                ];
            }

            if ($doc?->is_delivery_completed) {
                $lifecycleTimeline[] = [
                    'label' => 'Delivery Completed',
                    'date' => $doc->delivery_completed_date,
                ];
            }

            if ($doc?->is_payment_completed) {
                $lifecycleTimeline[] = [
                    'label' => 'Payment Completed',
                    'date' => $doc->payment_completed_date,
                ];
            }

            if ($requestData->supplierRating) {
                $lifecycleTimeline[] = [
                    'label' => 'Supplier Rated',
                    'date' => $requestData->supplierRating->created_at?->format('Y-m-d'),
                ];
            }

            if ($requestData->status === 'closed') {
                $lifecycleTimeline[] = [
                    'label' => 'Closed',
                    'date' => $requestData->updated_at?->format('Y-m-d'),
                ];
            }

            // Generate PDF
            $pdf = Pdf::loadView('pdf.request_details', [
                'request' => $requestData,
                'workflowTimeline' => $workflowTimeline,
                'lifecycleTimeline' => $lifecycleTimeline,
            ])->setPaper('A4', 'portrait');

            return $pdf->download('request-'.$requestData->request_id.'.pdf');

        } catch (\Exception $e) {
            Log::error('PDF Error: '.$e->getMessage());
            abort(500, 'Failed to generate PDF');
        }
    }

    // public function downloadRequestPdf($id)
    // {
    //     $requestModel = ModelsRequest::with([
    //         'userData',
    //         'workflowHistory.role',
    //         'workflowHistory.assignedUser',
    //         'workflowHistory.workflowStep',
    //         'requestDetailsDocuments',
    //     ])->findOrFail($id);

    //     $workflowHistory = collect($requestModel->workflowHistory)->map(function ($w) {
    //         return [
    //             'stage' => $w->workflowStep?->name ?? 'N/A',
    //             'role' => $w->role?->name ?? 'N/A',
    //             'assigned_user' => $w->assignedUser?->name ?? 'N/A',
    //             'status' => $w->status ?? 'pending',
    //             'date' => optional($w->updated_at)->format('Y-m-d') ?? 'N/A',
    //         ];
    //     })->values()->toArray();

    //     $documents = collect($requestModel->requestDetailsDocuments)->map(fn ($doc) => [
    //         'document_id' => $doc->document_id,
    //         'document' => $doc->document ?? 'N/A',
    //     ])->toArray();

    //     $data = [
    //         'request_id' => $requestModel->request_id,
    //         'description' => $requestModel->description,
    //         'status' => $requestModel->status,
    //         'requested_by' => [
    //             'name' => $requestModel->userData?->name,
    //         ],
    //         'workflow_history' => $workflowHistory,
    //         'documents' => $documents,
    //     ];

    //     $pdf = Pdf::loadView('pdf.request_details', [
    //         'request' => $data,
    //     ])->setPaper('a4', 'portrait');

    //     return response($pdf->output(), 200)
    //         ->header('Content-Type', 'application/pdf')
    //         ->header(
    //             'Content-Disposition',
    //             'attachment; filename="request_'.$requestModel->request_id.'.pdf"'
    //         );
    // }

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
