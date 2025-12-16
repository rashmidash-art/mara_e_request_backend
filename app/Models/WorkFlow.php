<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WorkFlow extends Model
{
    use HasFactory;

    protected $fillable = ['workflow_id', 'categori_id', 'request_type_id', 'name', 'steps', 'description', 'status'];

    public function steps()
    {
        return $this->hasMany(WorkflowStep::class, 'workflow_id', 'id')
            ->orderBy('order_id', 'asc');
    }

    // Workflow Role Assignments
    public function roles()
    {
        return $this->hasMany(WorkflowRoleAssign::class, 'workflow_id', 'id');
    }

    // Workflow Escalations
    public function escalations()
    {
        return $this->hasMany(Escalations::class, 'workflow_id', 'id');
    }

    protected static function booted()
    {
        static::deleting(function ($workflow) {
            // Order matters
            $workflow->roles()->delete();
            $workflow->escalations()->delete();
            $workflow->steps()->delete();
        });
    }
}
