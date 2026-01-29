<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Request extends Model
{
    use HasFactory;

    // protected $fillable = ['request_id', 'entiti', 'user','request_type','category','department','amount','description','supplier_id','expected_date','priority','behalf_of','behalf_of_department','business_justification','status'];

    protected $fillable = [
        'request_id',
        'entiti',
        'user',
        'request_type',
        'category',
        'department',
        'budget_code',
        'amount',
        'description',
        'supplier_id',
        'expected_date',
        'priority',
        'behalf_of',
        'behalf_of_department',
        'behalf_of_buget_code',
        'business_justification',
        'status',
    ];

    // public function currentWorkflowRole()
    // {
    //     return $this->hasOne(RequestWorkflowDetails::class, 'request_id', 'request_id')
    //         ->latest('id') // get latest workflow step
    //         ->with('role');
    // }

    public function currentWorkflowRole()
    {
        return $this->hasOne(RequestWorkflowDetails::class, 'request_id', 'request_id')
            ->latest('id');
    }

    public function workflowUsers()
    {
        return $this->hasMany(RequestWorkflowDetails::class, 'request_id', 'request_id')
            ->with('role', 'assignedUser', 'workflowStep')
            ->orderBy('workflow_step_id', 'asc');
    }

    public function pendingWorkflowRoles()
    {
        return $this->hasMany(RequestWorkflowDetails::class, 'request_id', 'request_id')
            ->where('status', 'pending')
            ->with([
                'workflowStep:id,name,order_id',
                'role:id,name,display_name',
                'assignedUser:id,name',
            ])
            ->orderBy('workflow_step_id', 'asc');
    }

    public function entityData()
    {
        return $this->belongsTo(Entiti::class, 'entiti');
    }

    public function userData()
    {
        return $this->belongsTo(User::class, 'user');
    }

    public function requestTypeData()
    {
        return $this->belongsTo(RequestType::class, 'request_type');
    }

    public function categoryData()
    {
        return $this->belongsTo(Category::class, 'category');
    }

    public function budgetCode()
    {
        return $this->belongsTo(BudgetCode::class, 'budget_code');
    }

    public function departmentData()
    {
        return $this->belongsTo(Department::class, 'department');
    }

    public function supplierData()
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function documents()
    {
        return $this->hasMany(RequestDocument::class, 'request_id', 'request_id');
    }

    public function workflowDetails()
    {
        return $this->hasMany(RequestWorkflowDetails::class, 'request_id', 'request_id');
    }

    public function workflowHistory()
    {
        return $this->hasMany(RequestWorkflowDetails::class, 'request_id', 'request_id')
            ->with(['role', 'assignedUser'])
            ->orderBy('id', 'asc');  // Ensures full timeline step-by-step
    }

    public function requestDetailsDocuments()
    {
        return $this->hasOne(RequestDetailsDocuments::class, 'request_id', 'request_id');
    }

    public function supplierRating()
    {
        return $this->hasOne(SupplierRating::class, 'request_id', 'request_id');
    }

    public function getFinalStatus()
    {
        // Step 1-5: Workflow statuses

        if ($this->status === 'closed') {
            return [
                'final_status' => 'Closed',
                'pending_by' => 'Completed',
            ];
        }

        if ($this->status === 'draft') {
            return [
                'final_status' => 'Draft',
                'pending_by' => 'Request Submission',
            ];
        }

        if ($this->status === 'withdraw') {
            return [
                'final_status' => 'Withdrawn',
                'pending_by' => 'Withdrawn',
            ];
        }

        $steps = $this->workflowUsers()->get();

        if ($steps->contains('status', 'rejected')) {
            return [
                'final_status' => 'Rejected',
                'pending_by' => 'Returned',
            ];
        }

        if ($steps->every(fn ($s) => $s->status === 'pending')) {
            return [
                'final_status' => 'Submitted',
                'pending_by' => $steps->first()?->role?->name,
            ];
        }

        if (
            $steps->contains('status', 'approved') &&
            $steps->contains('status', 'pending')
        ) {
            return [
                'final_status' => 'In Approval',
                'pending_by' => $steps->firstWhere('status', 'pending')?->role?->name,
            ];
        }

        if ($steps->isNotEmpty() && $steps->every(fn ($s) => $s->status === 'approved')) {
            // Workflow approved — now check document status
            $doc = $this->requestDetailsDocuments; // Make sure relation is defined

            if ($doc) {
                if ($doc->is_payment_completed == 1) {
                    return [
                        'final_status' => 'Payment Completed',
                        'pending_by' => 'Payment Completed',
                    ];
                }

                if ($doc->is_delivery_completed == 1) {
                    return [
                        'final_status' => 'Delivery Completed',
                        'pending_by' => 'Upload Payment',
                    ];
                }

                if ($doc->is_po_created == 1) {
                    return [
                        'final_status' => 'PO Created',
                        'pending_by' => 'Upload Delivery',
                    ];
                }

                return [
                    'final_status' => 'Approved',
                    'pending_by' => 'Upload PO',
                ];
            }

            // No documents yet
            return [
                'final_status' => 'Approved',
                'pending_by' => 'Upload PO',
            ];
        }

        // fallback
        return [
            'final_status' => ucfirst($this->status),
            'pending_by' => null,
        ];
    }

    // public function getFinalStatus()
    // {

    //     if ($this->status === 'draft') {
    //         return [
    //             'final_status' => 'Draft',
    //             'pending_by' => 'Request Submission',
    //         ];
    //     }

    //     $steps = $this->workflowUsers()->get();

    //     if ($this->status === 'withdraw') {
    //         return [
    //             'final_status' => 'withdraw',
    //             'pending_by' => null,
    //         ];
    //     }
    //     if ($steps->contains('status', 'withdraw')) {
    //         return [
    //             'final_status' => 'withdraw',
    //             'pending_by' => 'withdraw',
    //         ];
    //     }
    //     if ($steps->contains('status', 'withdraw')) {
    //         return [
    //             'final_status' => 'withdraw',
    //             'pending_by' => 'withdraw',
    //         ];
    //     }

    //     //  If any rejected
    //     if ($steps->contains('status', 'rejected')) {
    //         return [
    //             'final_status' => 'Rejected',
    //             'pending_by' => 'Returned',
    //         ];
    //     }

    //     //  If all approved
    //     if ($steps->isNotEmpty() && $steps->every(fn ($s) => $s->status === 'approved')) {
    //         return [
    //             'final_status' => 'Approved',
    //             'pending_by' => 'Completed',
    //         ];
    //     }

    //     //  If any pending
    //     $pending = $steps->firstWhere('status', 'pending');
    //     if ($pending) {
    //         return [
    //             'final_status' => 'Pending',
    //             'pending_by' => $pending->role?->name ?? 'In Progress',
    //         ];
    //     }

    //     // Fallback
    //     return [
    //         'final_status' => ucfirst($this->status),
    //         'pending_by' => null,
    //     ];
    // }
}
