<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Manager extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'entiti_id','department_id','employee_id', 'name', 'status'];

    public function departments()
{
    return $this->hasMany(Department::class, 'manager_id');
}
}
