<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Laravel\Passport\HasApiTokens;

class Supplier extends Model
{
    use HasApiTokens;

    protected $fillable = [
        "name",
        "bc_code",
        "email",
        "phone",
        "contact_persion_name",
        "address",
        "tax_id",
        "regi_no",
        "categories",
        "entiti_id",
        "departments",
        "regi_certificates",
        "tax_certificates",
        "insurance_certificates",
        "status",
    ];


    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function department()
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

}
