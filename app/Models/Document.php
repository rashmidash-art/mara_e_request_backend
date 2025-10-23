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
        'roles',
        'file_formats',
        'categories',
        'max_count',
        'expiry_days',
        'description',
        'status',
        'is_mandatory',
        'is_enable'
    ];


}
