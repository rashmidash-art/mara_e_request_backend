<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

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
        'amount',
        'description',
        'supplier_id',
        'expected_date',
        'priority',
        'behalf_of',
        'behalf_of_department',
        'business_justification',
        'status'
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

    public function pendingWorkflowRoles()
    {
        return $this->hasMany(RequestWorkflowDetails::class, 'request_id', 'request_id')
            ->where('status', 'pending')
            ->with([
                'workflowStep:id,name,order_id',
                'role:id,name,display_name',
                'assignedUser:id,name'
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
}
