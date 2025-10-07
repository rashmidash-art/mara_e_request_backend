<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Laravel\Passport\HasApiTokens;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Category extends Model
{
    use HasApiTokens ,HasFactory;

    protected $fillable = ["name","description","status"];


public function suppliers()
    {
        return $this->hasMany(Supplier::class, 'category_id');
    }
}
