<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Auth;

class AuthRoleHelper
{
    /**
     * Create a new class instance.
     */
    public function __construct()
    {
        //
    }
    public static function loginType(): ?string
    {
        return Auth::user()->login_type ?? null;
    }

    /**
     * Check if user is super admin
     */
    public static function isSuperAdmin(): bool
    {
        return self::loginType() === 'superadmin';
    }

    /**
     * Check if user is entity
     */
    public static function isEntity(): bool
    {
        return self::loginType() === 'entity';
    }

    /**
     * Return entity_scope for entity users
     */
    public static function getEntityId(): ?int
    {
        return Auth::user()->entity_scope ?? null;
    }
}
