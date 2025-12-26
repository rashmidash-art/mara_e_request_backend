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

    public function getFinalStatus()
    {
        $workflowSteps = $this->workflowUsers()
            ->with('role:id,name')
            ->orderBy('workflow_step_id', 'asc') // Order by workflow step
            ->get();

        if ($workflowSteps->isEmpty()) {
            return [
                'final_status' => ucfirst($this->status), // Default status based on request's current status
                'pending_by' => null,
            ];
        }

        if ($workflowSteps->contains('status', 'rejected')) {
            return [
                'final_status' => 'Rejected',
                'pending_by' => null,
            ];
        }

        $firstPending = $workflowSteps->firstWhere('status', 'pending');
        if ($firstPending) {
            return [
                'final_status' => 'Pending',
                'pending_by' => $firstPending->role?->name ?? null, // Return the role of the person who is pending
            ];
        }

        if ($workflowSteps->every(fn ($step) => $step->status === 'approved')) {
            return [
                'final_status' => 'Approved',
                'pending_by' => null,
            ];
        }

        return [
            'final_status' => ucfirst($this->status),
            'pending_by' => null,
        ];

    }
}
