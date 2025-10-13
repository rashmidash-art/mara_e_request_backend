<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Entiti;

class PasswordResetContrller extends Controller
{
    public function sendResetLink(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        // Check if the email belongs to a User or an Entiti
        $user = User::where('email', $request->email)->first();
        $entity = Entiti::where('email', $request->email)->first();

        if (!$user && !$entity) {
            return response()->json([
                'status' => 'error',
                'message' => 'No user or entity found with this email.'
            ], 404);
        }

        if ($user) {
            // Generate token for user

            $token = Password::broker('users')->createToken($user);
            $user->sendPasswordResetNotification($token);
            $target = 'user';
        } else {
            // Generate token for entity
            $token = Password::broker('entitis')->createToken($entity);

            // Optional: Send entity-specific email notification (if implemented)
            // $entity->sendPasswordResetNotification($token);

            $target = 'entity';
        }

        return response()->json([
            'status' => 'success',
            'message' => "Reset token created successfully for $target.",
            'token' => $token,
        ], 200);
    }

    /**
     * Reset password for both Users and entitis
     */
    public function reset(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'token' => 'required|string',
            'password' => 'required|string|min:6|confirmed',
        ]);

        // Determine whether it's a User or an Entity
        $isEntity = Entiti::where('email', $request->email)->exists();
        $broker = $isEntity ? 'entitis' : 'users';

        $status = Password::broker($broker)->reset(
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
                'message' => __($status),
            ], 200);
        }

        return response()->json([
            'status' => 'error',
            'message' => __($status),
        ], 400);
    }
}
