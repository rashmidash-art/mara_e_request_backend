<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;

class Authenticate extends Middleware
{
    /**
     * Override redirectTo: not needed for API
     */
    protected function redirectTo($request)
    {
        return null; // No redirect
    }

    /**
     * Force JSON response for unauthenticated requests
     */
    protected function unauthenticated($request, array $guards)
    {
        abort(response()->json([
            'status' => 'error',
            'message' => 'Unauthorized',
        ], 401));
    }
}
