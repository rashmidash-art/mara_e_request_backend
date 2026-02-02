<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Document extends Model
{
    use HasFactory;
    protected $fillable = [
        'name',
        'entiti_id',
        'work_flow_steps',
        'workflow_id',
        'roles',
        'file_formats',
        'categories',
        'request_types',
        'max_count_type',
        'max_count',
        'expiry_days',
        'description',
        'status',
        'is_mandatory',
        'is_enable'
    ];


}
