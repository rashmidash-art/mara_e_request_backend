<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class RequestWorkflowDetails extends Model
{
    use HasFactory;
    protected $fillable = ['request_id', 'workflow_id', 'workflow_step_id', 'workflow_role_id', 'action_taken_by', 'remark', 'status', 'is_sendback', 'sendback_remark'];


    /** Request header info */
    public function request()
    {
        return $this->belongsTo(Request::class, 'request_id', 'request_id');
    }

    /** Workflow step */
    public function workflowStep()
    {
        return $this->belongsTo(WorkflowStep::class, 'workflow_step_id', 'id');
    }

    /** Workflow */
    public function workflow()
    {
        return $this->belongsTo(WorkFlow::class, 'workflow_id', 'id');
    }

    /** Role assigned to this workflow step */
    public function role()
    {
        return $this->belongsTo(Role::class, 'workflow_role_id', 'id');
    }
}
