<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;


class Entiti extends Model
{
    use HasFactory;
     protected $fillable = [
        "name",
        "company_code",
        "budget",
        "description",
        "status"
    ];

    public function departments()
{
    return $this->hasMany(Department::class, 'entiti_id');
}

}
