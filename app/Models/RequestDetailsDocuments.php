<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RequestDetailsDocuments extends Model
{
    use HasFactory;

    protected $fillable = [
        'request_details_id',
        'request_id',
        'is_po_created',
        'po_number',
        'po_date',
        'po_documents',
        'is_delivery_completed',
        'delivery_completed_number',
        'delivery_completed_date',
        'delivery_completed_documents',
        'is_payment_completed',
        'payment_completed_number',
        'payment_completed_date',
        'payment_completed_documents',
        'status',
    ];

    public function getCurrentStatus()
    {
        // Step 1: PO not yet created
        if ($this->is_po_created != 1) {
            return 'Approved';
        }

        // Step 2: PO created, Delivery not completed
        if ($this->is_po_created == 1 && $this->is_delivery_completed != 1) {
            return 'PO Created';
        }

        // Step 3: Delivery completed, Payment not completed
        if ($this->is_delivery_completed == 1 && $this->is_payment_completed != 1) {
            return 'Delivery Completed';
        }

        // Step 4: Payment completed
        if ($this->is_payment_completed == 1) {
            return 'Payment Completed';
        }

        return 'Unknown';
    }
}
