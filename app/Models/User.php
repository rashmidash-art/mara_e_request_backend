<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\HasApiTokens;
use App\Notifications\ResetPasswordNotification;


class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'employee_id',
        'entiti_id',
        'department_id',
        'loa',
        'signature',
        'status',
        'user_type'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }



    public function sendPasswordResetNotification($token)
    {
        $this->notify(new ResetPasswordNotification($token));
    }



    public function roles()
    {
        return $this->belongsToMany(Role::class, 'role_user');
    }


    public function permissions()
    {
        // Get all permissions via roles
        return $this->roles()->with('permissions')
            ->get() // gets all roles
            ->pluck('permissions') // pluck each role's permissions
            ->flatten() // merge into single collection
            ->pluck('name') // get permission names
            ->unique()
            ->values(); // reset keys
    }

    public function hasPermission($permission)
    {
        // Ensure string comparison
        return $this->permissions()->contains(function ($permName) use ($permission) {
            return trim($permName) === trim($permission);
        });
    }



}
