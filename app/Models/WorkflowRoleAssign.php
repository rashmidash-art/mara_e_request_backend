<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WorkflowRoleAssign extends Model
{
    use HasFactory;

    protected $fillable = [
        'workflow_id',
        'entity_id',
        'step_id',
        'role_id',
        'approval_logic',
        'specific_user',
        'user_id',
        'remark',
    ];

    public function workflow()
    {
        return $this->belongsTo(WorkFlow::class, 'workflow_id', 'id');
    }

    public function step()
    {
        return $this->belongsTo(WorkflowStep::class, 'step_id', 'id');
    }

    public function role()
    {
        return $this->belongsTo(Role::class, 'role_id', 'id');
    }

    // public function assignedUser()
    // {
    //     return $this->belongsTo(User::class, 'user_id', 'id');
    // }

    public function assignedUsers()
    {
        if ($this->specific_user == 1) {
            $user_ids = $this->user_id;

            if ($user_ids) {
                if (is_array($user_ids = json_decode($user_ids, true))) {
                    return User::whereIn('id', $user_ids)->get();
                } else {
                    return User::where('id', $user_ids)->get();
                }
            }
        }

        return collect();
    }

    public function requestHeader()
    {
        return $this->belongsTo(Request::class, 'request_id', 'request_id')
            ->with([
                'userData',
                'entityData',
                'requestTypeData',
                'categoryData',
                'departmentData',
                'supplierData',
            ]);
    }
}
