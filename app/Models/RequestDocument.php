<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class RequestDocument extends Model
{
    use HasFactory;

      protected $fillable = ['request_id','document_id','document'];

}
