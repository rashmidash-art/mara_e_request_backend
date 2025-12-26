<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Passport\HasApiTokens;

class BudgetCode extends Model
{
    use HasApiTokens ,HasFactory;

    protected $fillable = ['entity_id', 'department_id', 'budget_code', 'budget_limit', 'description', 'status'];

    public function entity()
    {
        return $this->belongsTo(Entiti::class, 'entity_id');
    }

    public function department()
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function requests()
    {
        return $this->hasMany(Request::class, 'budget_code', 'id');
    }
}
