<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RequestType extends Model
{
    use HasFactory;

    protected $fillable = ['categori_id', 'request_code','budget','name','administrative_request','loa_validation', 'descripton', 'status'];

    public function category()
    {
        return $this->belongsTo(Category::class, 'categori_id');
    }

}
