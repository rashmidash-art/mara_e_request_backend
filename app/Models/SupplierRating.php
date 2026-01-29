<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SupplierRating extends Model
{
   use HasFactory;

    protected $fillable = [
        'request_id',
        'user_id',
        'supplier_id',
        'comment',
        'rating',
        'status'
    ];
}
