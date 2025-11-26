<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class WorkFlow extends Model
{
    use HasFactory;

    protected $fillable = ['workflow_id', 'categori_id', 'request_type_id', 'name', 'steps', 'description', 'status'];


    public function steps()
    {
        return $this->hasMany(WorkflowStep::class, 'workflow_id', 'id')
            ->orderBy('order_id', 'asc');
    }
}
