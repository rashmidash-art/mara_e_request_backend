<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Document extends Model
{
    use HasFactory;
    protected $fillable = [
        'name',
        'work_flow_ids',
        'role_ids',
        'fileformat_ids',
        'categorie_ids',
        'max_count',
        'expiry_days',
        'description',
        'status',
        'is_mandatory',
        'is_enable'
    ];

    protected $casts = [
        'work_flow_ids' => 'array',
        'role_ids' => 'array',
        'fileformat_ids' => 'array',
        'categorie_ids' => 'array',
    ];
}
