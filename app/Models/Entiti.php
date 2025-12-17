<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Passport\HasApiTokens; // or use Laravel\Sanctum\HasApiTokens
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Notifications\ResetPasswordNotification;

class Entiti extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        "name",
        "email",
        "company_code",
        "budget",
        "description",
        "password",
        "status"
    ];

    protected $hidden = [
        'password', 'remember_token',
    ];

    public function sendPasswordResetNotification($token)
    {
        $this->notify(new ResetPasswordNotification($token));
    }

    public function departments()
    {
        return $this->hasMany(Department::class, 'entiti_id');
    }

     public function documents()
    {
        return $this->hasMany(Document::class, 'entiti_id');
    }


    public function workflow()
    {
        return $this->hasMany(WorkFlow::class, 'entity_id', 'id');
    }

}
