<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WorkflowUser extends Model
{
    use HasFactory;

    protected $fillable = [
        'entity_id',
        'workflow_id',
        'step_id',
        'role_id',
        'logic',
        'user_id',
        'status',
    ];

    public function entity()
    {
        return $this->belongsTo(Entiti::class, 'entity_id', 'id');
    }

    // Relation to Workflow
    public function workflow()
    {
        return $this->belongsTo(WorkFlow::class, 'workflow_id', 'id');
    }

    // Relation to Workflow Step
    public function step()
    {
        return $this->belongsTo(WorkflowStep::class, 'step_id', 'id');
    }

    // Relation to Role
    public function role()
    {
        return $this->belongsTo(Role::class, 'role_id', 'id');
    }

    // Relation to User
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
