<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\User;
use App\Models\Entiti;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class LoginController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required'
        ]);

        // Check in users table first
        $user = User::where('email', $request->email)->first();
        Log::info('User found: ' . ($user ? $user->id : 'none'));

        if ($user) {
            Log::info('Password from DB: ' . $user->password);
            Log::info('Password from request: ' . $request->password);

            if (!Hash::check($request->password, $user->password)) {
                Log::warning('Password check failed for user ID: ' . $user->id);
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Invalid credentials'
                ], 401);
            }

            Log::info('Password matched for user ID: ' . $user->id);

            try {
                $token = $user->createToken('User Token')->accessToken;
                Log::info('Token created successfully for user ID: ' . $user->id);
            } catch (\Exception $e) {
                Log::error(message: 'Token creation failed for user ID: ' . $user->id . ' Error: ' . $e->getMessage());
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Token creation failed',
                    'error_message' => $e->getMessage(),
                ], 500);
            }

            $permissions = $user->user_type == 0
                ? Permission::pluck('name')->toArray() // Super Admin → all permissions
                : $user->permissions()->pluck('name')->toArray(); // Normal user → assigned permissions

            return response()->json([
                'status'       => 'success',
                'type'         => $user->user_type == 0 ? 'superadmin' : 'user',
                'user'         => $user,
                'token'        => $token,
                'permissions'  => $permissions,
                'entity_scope' => null,
            ], 200);
        }


        // Check in entitis table
        $entity = Entiti::where('email', $request->email)->first();
        Log::info('Entity found: ' . ($entity ? $entity->id : 'none'));

        if ($entity && Hash::check($request->password, $entity->password)) {
            Log::info('Entity password from DB: ' . $entity->password);
            Log::info('Entity password from request: ' . $request->password);
            try {
                $token = $entity->createToken('Entity Token')->accessToken;
                Log::info('Token created successfully for entity: ' . $entity->id);

                // Entity → all permissions except entities.*
                $permissions = Permission::where('name', 'entities.view')->pluck('name')->toArray();


                return response()->json([
                    'status'       => 'success',
                    'type'         => 'entity',
                    'user'         => $entity,
                    'token'        => $token,
                    'permissions'  => $permissions,
                    'entity_scope' => $entity->id, // Limit data access to this entity
                ], 200);
            } catch (\Exception $e) {
                Log::error('Token creation failed: ' . $e->getMessage());
                return response()->json([
                    'status' => 'error',
                    'message' => 'Token creation failed'
                ], 500);
            }
        }

        // If neither user nor entity matched
        return response()->json(['status' => 'error', 'message' => 'Invalid credentials'], 401);
    }

    public function logout(Request $request)
    {
        $request->user()->tokens()->delete();

        return response()->json([
            'status'  => 'success',
            'message' => 'Logged out successfully'
        ]);
    }
}
