<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class RequestType extends Model
{
    use HasFactory;

    protected $fillable = ['categori_id', 'request_code', 'name', 'descripton', 'status'];
}
