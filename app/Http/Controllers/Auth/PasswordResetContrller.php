<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class PasswordResetContrller extends Controller
{
    public function sendResetLink(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $user = \App\Models\User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => "No user found with this email."
            ], 404);
        }

        // Generate token manually
        $token = Password::createToken($user);

        // Optional: send email as well
        $user->sendPasswordResetNotification($token);

        return response()->json([
            'status' => 'success',
            'message' => "Reset token created successfully.",
            'token' => $token
        ], 200);
    }


    // Reset password
    public function reset(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'token' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                ])->save();
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return response()->json([
                'status' => 'success',
                'message' => __($status)
            ], 200);
        }

        return response()->json([
            'status' => 'error',
            'message' => __($status)
        ], 400);
    }
}
