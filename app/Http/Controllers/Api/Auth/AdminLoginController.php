<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminLoginController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required'
        ]);
        $credentials = $request->only('email', 'password');
        if (!Auth::attempt($credentials)) {
            return response()->json(['error' => 'Invalid credentials'], 401);
        }
        $user = Auth::user();
        $token = $user->createToken('API Token')->accessToken;
        return response()->json([
            'user'  => $user,
            'token' => $token,
        ]);
    }


    public function logout(Request $request)
    {
        $token = $request->user()->currentAccessToken();

        return response()->json([
            'message' => 'Logged out successfully'
        ]);
    }
}
