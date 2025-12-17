<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class WorkflowStep extends Model
{
     use HasFactory;

     protected $fillable = ['order_id','workflow_id','entity_id','name','form_type','sla_hour','description','escalation','status'];

     public function workflow()
    {
        return $this->belongsTo(WorkFlow::class, 'workflow_id', 'id');
    }
}
