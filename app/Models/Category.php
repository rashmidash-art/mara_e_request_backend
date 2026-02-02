<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Passport\HasApiTokens;

class Category extends Model
{
    use HasApiTokens ,HasFactory;

    protected $fillable = ['name', 'description', 'status'];

    public function suppliers()
    {
        return $this->hasMany(Supplier::class, 'category_id');
    }

    public function requestTypes()
    {
        return $this->hasMany(RequestType::class, 'categori_id');
    }
}
