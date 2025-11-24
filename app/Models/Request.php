<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Request extends Model
{
    use HasFactory;

    // protected $fillable = ['request_id', 'entiti', 'user','request_type','category','department','amount','description','supplier_id','expected_date','priority','behalf_of','behalf_of_department','business_justification','status'];

    protected $fillable = [
        'request_id',
        'entiti',
        'user',
        'request_type',
        'category',
        'department',
        'amount',
        'description',
        'supplier_id',
        'expected_date',
        'priority',
        'behalf_of',
        'behalf_of_department',
        'business_justification',
        'status'
    ];
}
