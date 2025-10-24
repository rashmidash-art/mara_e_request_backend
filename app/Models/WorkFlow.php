<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class WorkFlow extends Model
{
    use HasFactory;

    protected $fillable = ['workflow_id','categori_id', 'name','steps', 'description', 'status'];
}
