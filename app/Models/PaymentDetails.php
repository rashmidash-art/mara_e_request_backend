<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentDetails extends Model
{
    use HasFactory;

    protected $fillable = [
        'request_id',
        'is_payment_completed',
        'payment_number',
        'payment_date',
        'payment_documents',
        'payment_amount',
        'status',
    ];
}
