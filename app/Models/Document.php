<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Document extends Model
{
    use HasFactory;
    protected $fillable = [
        'name',
        'work_flow_id',
        'role_id',
        'fileformat_id',
        'categorie_id',
        'max_count',
        'expiry_days',
        'description',
        'status',
        'is_mandatory',
        'is_enable'
    ];


}
