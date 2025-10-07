<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Role extends Model
{

     use HasFactory;

    protected $fillable = [
        'name',
        'display_name',
        'description',
        'status',
    ];

    public function users()
    {
        return $this->belongsToMany(User::class, 'role_user');
    }
}
