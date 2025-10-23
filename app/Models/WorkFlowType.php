<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class WorkFlowType  extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'status'];

    public function departments()
    {
        return $this->hasMany(Department::class, 'work_flow_type_id');
    }
}
