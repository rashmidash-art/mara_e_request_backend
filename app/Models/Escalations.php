<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Escalations extends Model
{
    use HasFactory;

    protected $fillable = [

        'workflow_id',
        'entity_id',
        'step_id',
        'role_id',
        'user_id',
        'description',
        'enable_rule',
        'enable_notification',
        'notify_type',
        'enable_mail',
        'sla_hour',
        'escalation_hour',
        'status',

    ];

    public function entityData()
    {
        return $this->belongsTo(Entiti::class, 'entiti');
    }

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

    public function assignedUser()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
