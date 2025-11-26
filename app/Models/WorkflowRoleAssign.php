<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class WorkflowRoleAssign extends Model
{
    use HasFactory;

    protected $fillable = [
        'workflow_id',
        'step_id',
        'role_id',
        'approval_logic',
        'specific_user',
        'user_id',
        'remark'
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
}
