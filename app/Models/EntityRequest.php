<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class EntityRequest extends Model
{
     use HasFactory;

      protected $fillable = [
       "entity_id",
       "categore_id",
       "request_type_id",
       "status"
    ];

    public function entity()
    {
        return $this->belongsTo(Entiti::class, 'entiti_id');
    }


    public function categore()
    {
        return $this->belongsTo(Category::class, 'categore_id');
    }

     public function requestType()
    {
        return $this->belongsTo(RequestType::class, 'request_type_id');
    }

}
