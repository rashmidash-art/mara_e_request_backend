<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PoUploadDetalils extends Model
{
    use HasFactory;

    protected $fillable = [
        'request_id',
        'is_po_created',
        'po_number',
        'po_date',
        'po_amount',
        'po_documents',
        'status',
    ];

}
