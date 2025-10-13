<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Department extends Model
{

    use HasFactory;
    protected $fillable = [
        'entiti_id',
        'manager_id',
        'name',
        'department_code',
        'bc_dimention_value',
        'enable_cost_center',
        'work_flow_id',
        'budget',
        'description',
        'status'
    ];


    public function entity()
    {
        return $this->belongsTo(Entiti::class, 'entiti_id');
    }

    // Department belongs to a manager
    public function manager()
    {
        return $this->belongsTo(Manager::class, 'manager_id');
    }

    // Department belongs to a workflow
    public function workflow()
    {
        return $this->belongsTo(WorkFlow::class, 'work_flow_id');
    }

    public function suppliers()
    {
        return $this->hasMany(Supplier::class, 'department_id');
    }

    public function users()
    {
        return $this->hasMany(User::class, 'department_id', 'id');
    }
}
