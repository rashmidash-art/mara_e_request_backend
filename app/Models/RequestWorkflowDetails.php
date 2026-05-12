<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RequestWorkflowDetails extends Model
{
    use HasFactory;

    protected $fillable = ['request_id', 'workflow_id', 'workflow_step_id', 'workflow_role_id', 'approval_logic', 'action_taken_by', 'remark', 'documents', 'status', 'is_mail_sent', 'is_sendback', 'sendback_remark', 'assigned_user_id'];

    protected $casts = [
        'documents' => 'array',
        // 'is_mail_sent' => 'boolean',
        'is_sendback' => 'boolean',
    ];

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

    public function assignedUser()
    {
        return $this->belongsTo(User::class, 'assigned_user_id', 'id');
    }

    public function actionTakenBy()
    {
        return $this->belongsTo(User::class, 'action_taken_by', 'id');
    }

    /**
     * Get full URLs for uploaded workflow documents.
     */
    public function getDocumentUrlsAttribute()
    {
        if (empty($this->documents)) {
            return [];
        }

        return collect($this->documents)->map(function ($file) {
            return [
                'name' => $file,
                'url' => asset('storage/workflow_documents/'.$file),
            ];
        })->toArray();
    }
}
