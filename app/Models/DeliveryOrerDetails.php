<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeliveryOrerDetails extends Model
{
     use HasFactory;

    protected $fillable = [
        'request_id',
        'is_delivery_completed',
        'delivery_number',
        'delivery_date',
        'delivery_quantity',
        'delivery_documents',
        'status',
    ];
}
