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

    public const DRAFT = 'draft';

    public const SUBMITTED = 'submitted';

    public const WITHDRAW = 'withdraw';

    public const IN_APPROVAL = 'in_approval';

    public const APPROVE = 'approved';

    public const REJECT = 'reject';

    public const PO_CREATED = 'po_created';

    public const DELIVERY_COMPLETED = 'delivery_completed';

    public const PAYMENT_COMPLETED = 'payment_completed';

    public const SUPPLIER_RATING = 'supplier_rating';

    public const CLOSED = 'closed';

    public function recalculateStatus()
    {
        if (in_array($this->status, [
            self::CLOSED,
            self::WITHDRAW,
            self::REJECT,
        ])) {
            return;
        }

        $steps = $this->workflowDetails()->get();

        if ($steps->isEmpty()) {
            return;
        }

        /*
        |--------------------------------------------------------------------------
        |  REJECT
        |--------------------------------------------------------------------------
        */
        if ($steps->contains('status', 'rejected')) {
            $this->changeStatus(self::REJECT);

            return;
        }

        /*
        |--------------------------------------------------------------------------
        |  IN APPROVAL (MIXED)
        |--------------------------------------------------------------------------
        */
        if (
            $steps->contains('status', 'approved') &&
            $steps->contains('status', 'pending')
        ) {
            $this->changeStatus(self::IN_APPROVAL);

            return;
        }

        /*
        |--------------------------------------------------------------------------
        |  SUBMITTED (ALL PENDING)
        |--------------------------------------------------------------------------
        */
        if ($steps->every(fn ($s) => $s->status === 'pending')) {
            $this->changeStatus(self::SUBMITTED);

            return;
        }

        /*
        |--------------------------------------------------------------------------
        |  FULLY APPROVED
        |--------------------------------------------------------------------------
        */
        if ($steps->every(fn ($s) => $s->status === 'approved')) {

            if (! $this->poDetails()->exists()) {
                $this->changeStatus(self::APPROVE);

                return;
            }

            if ($this->payments()->where('is_payment_completed', 1)->exists()) {

                if (! $this->supplierRating()->exists()) {
                    $this->changeStatus(self::PAYMENT_COMPLETED);

                    return;
                }

                $this->changeStatus(self::SUPPLIER_RATING);

                return;
            }

            if ($this->deliveries()->where('is_delivery_completed', 1)->exists()) {
                $this->changeStatus(self::DELIVERY_COMPLETED);

                return;
            }

            $this->changeStatus(self::PO_CREATED);
        }
        // if ($steps->every(fn ($s) => $s->status === 'approved')) {

        //     if (! $this->poDetails()->exists()) {
        //         $this->changeStatus(self::APPROVE);

        //         return;
        //     }

        //     if (! $this->deliveries()
        //         ->where('is_delivery_completed', 1)
        //         ->exists()) {

        //         $this->changeStatus(self::PO_CREATED);

        //         return;
        //     }

        //     // Delivery completed but payment not completed
        //     if (! $this->payments()
        //         ->where('is_payment_completed', 1)
        //         ->exists()) {

        //         $this->changeStatus(self::DELIVERY_COMPLETED);

        //         return;
        //     }

        //     // Payment completed but rating not done
        //     if (! $this->supplierRating()->exists()) {

        //         $this->changeStatus(self::PAYMENT_COMPLETED);

        //         return;
        //     }

        //     // Everything done
        //     $this->changeStatus(self::SUPPLIER_RATING);

        //     return;
        // }
    }

    public function poDetails()
    {
        return $this->hasOne(PoUploadDetalils::class, 'request_id', 'request_id');
    }

    public function deliveries()
    {
        return $this->hasMany(DeliveryOrerDetails::class, 'request_id', 'request_id');
    }

    public function payments()
    {
        return $this->hasMany(PaymentDetails::class, 'request_id', 'request_id');
    }

    public function changeStatus($newStatus)
    {
        $this->update([
            'status' => $newStatus,
        ]);
    }
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

    public function currentWorkflowStep()
    {
        return $this->belongsTo(WorkflowStep::class, 'workflow_step_id');
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

    public function hasPoCreated()
    {
        return $this->poDetails()->exists();
    }

    public function hasDeliveryCompleted()
    {
        return $this->deliveries()
            ->where('is_delivery_completed', 1)
            ->exists();
    }

    public function hasPaymentCompleted()
    {
        return $this->payments()
            ->where('is_payment_completed', 1)
            ->exists();
    }

    public function hasSupplierRated()
    {
        return $this->supplierRating()->exists();
    }

    public function getFinalStatus()
    {
        //  Direct states
        if ($this->status === self::CLOSED) {
            return [
                'final_status' => 'Closed',
                'pending_by' => 'Completed',
            ];
        }

        if ($this->status === self::DRAFT) {
            return [
                'final_status' => 'Draft',
                'pending_by' => 'Request Submission',
            ];
        }

        if ($this->status === self::WITHDRAW) {
            return [
                'final_status' => 'Withdrawn',
                'pending_by' => 'Withdrawn',
            ];
        }

        $steps = $this->workflowDetails()->get();

        if ($steps->isEmpty()) {
            return [
                'final_status' => ucfirst($this->status),
                'pending_by' => null,
            ];
        }

        //  Rejected
        if ($steps->contains('status', 'rejected')) {
            return [
                'final_status' => 'Rejected',
                'pending_by' => 'Rejected',
            ];
        }

        //  Submitted (all pending)
        if ($steps->every(fn ($s) => $s->status === 'pending')) {
            return [
                'final_status' => 'Submitted',
                'pending_by' => optional($steps->first()->role)->name,
            ];
        }

        //  In Approval
        if ($steps->contains('status', 'approved') && $steps->contains('status', 'pending')) {

            $pendingStep = $steps->firstWhere('status', 'pending');

            return [
                'final_status' => 'In Approval',
                'pending_by' => optional($pendingStep?->role)->name,
            ];
        }

        //  Fully Approved → Process stages
        if ($steps->every(fn ($s) => $s->status === 'approved')) {

            if (! $this->hasPoCreated()) {
                return [
                    'final_status' => 'Approved',
                    'pending_by' => 'Upload PO',
                ];
            }

            // if (! $this->hasDeliveryCompleted()) {
            //     return [
            //         'final_status' => 'PO Created',
            //         'pending_by' => 'Upload Delivery',
            //     ];
            // }
            $deliveries = $this->deliveries()->count();

            $finalDelivery = $this->deliveries()
                ->where('is_delivery_completed', 1)
                ->exists();

            if ($deliveries > 0 && ! $finalDelivery) {

                $suffix = match ($deliveries) {
                    1 => '1st',
                    2 => '2nd',
                    3 => '3rd',
                    default => $deliveries.'th',
                };

                return [
                    'final_status' => "{$suffix} Delivery Completed",
                    'pending_by' => 'Upload Next Delivery',
                ];
            }
            $payments = $this->payments()->count();
            $finalPayment = $this->payments()
                ->where('is_payment_completed', 1)
                ->exists();

            if ($finalDelivery && $payments == 0) {
                return [
                    'final_status' => 'Delivery Completed',
                    'pending_by' => 'Upload Payment',
                ];
            }

            // if ($finalDelivery) {
            //     return [
            //         'final_status' => 'Delivery Completed',
            //         'pending_by' => 'Upload Payment',
            //     ];
            // }
            // if (! $this->hasPaymentCompleted()) {
            //     return [
            //         'final_status' => 'Delivery Completed',
            //         'pending_by' => 'Upload Payment',
            //     ];
            // }

            $payments = $this->payments()->count();
            $finalPayment = $this->payments()
                ->where('is_payment_completed', 1)
                ->exists();

            if ($payments > 0 && ! $finalPayment) {

                $suffix = match ($payments) {
                    1 => '1st',
                    2 => '2nd',
                    3 => '3rd',
                    default => $payments.'th',
                };

                return [
                    'final_status' => "{$suffix} Payment Completed",
                    'pending_by' => 'Upload Next Payment',
                ];
            }

            if ($finalPayment) {
                return [
                    'final_status' => 'Payment Completed',
                    'pending_by' => 'Rate Supplier',
                ];
            }
            if (! $this->hasSupplierRated()) {
                return [
                    'final_status' => 'Payment Completed',
                    'pending_by' => 'Rate Supplier',
                ];
            }

            return [
                'final_status' => 'Supplier Rated',
                'pending_by' => 'Completed',
            ];
        }

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
