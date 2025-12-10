<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Entiti;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class LoginController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        // -----------------------------------------------
        // 1️⃣ CHECK USER TABLE FIRST
        // -----------------------------------------------
        $user = User::where('email', $request->email)->first();
        Log::info('User lookup: '.($user ? $user->id : 'not found'));

        if ($user) {

            if (! Hash::check($request->password, $user->password)) {
                Log::warning("Password mismatch for user {$user->id}");

                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid credentials',
                ], 401);
            }

            Log::info("Password matched for user {$user->id}");

            try {
                $token = $user->createToken('User Token', [], 'auth')->accessToken;
            } catch (\Exception $e) {
                Log::error("Token creation failed for user {$user->id}: ".$e->getMessage());

                return response()->json([
                    'status' => 'error',
                    'message' => 'Token creation failed',
                    'error' => $e->getMessage(),
                ], 500);
            }

            // -------------------------
            // Permissions for USERS
            // -------------------------
            if ($user->user_type == 0) {
                // Super Admin → all permissions
                $permissions = Permission::pluck('name')->toArray();
            } else {
                // Normal user → permissions through roles
                $permissions = $user->permissions()->toArray();
            }

            $entityName = null;
            if ($user->entiti_id) {
                $entity = $user->entity;  // using relationship
                $entityName = $entity ? $entity->name : null;
            }

            return response()->json([
                'status' => 'success',
                'type' => $user->user_type == 0 ? 'superadmin' : 'user',
                'user' => $user,
                'entity_name' => $entityName,
                'token' => $token,
                'permissions' => $permissions,
                'entity_scope' => $user->entiti_id,
            ], 200);
        }

        // -----------------------------------------------
        // 2️⃣ CHECK ENTITY TABLE (entitis)
        // -----------------------------------------------
        $entity = Entiti::where('email', $request->email)->first();
        Log::info('Entity lookup: '.($entity ? $entity->id : 'not found'));

        if ($entity) {

            if (! Hash::check($request->password, $entity->password)) {
                Log::warning("Password mismatch for entity {$entity->id}");

                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid credentials',
                ], 401);
            }

            Log::info("Entity password matched for entity {$entity->id}");

            try {
                $token = $entity->createToken('Entity Token', [], 'entiti-api')->accessToken;
            } catch (\Exception $e) {
                Log::error('Entity token creation failed: '.$e->getMessage());

                return response()->json([
                    'status' => 'error',
                    'message' => 'Token creation failed',
                ], 500);
            }

            // -------------------------
            // Permissions for ENTITY USERS
            // Allowed only → entities.view
            // -------------------------
            $permissions = [
                'entities.view',
            ];

            return response()->json([
                'status' => 'success',
                'type' => 'entity',
                'user' => $entity,
                'token' => $token,
                'permissions' => $permissions,
                'entity_scope' => $entity->id,
                'entity_name'  => $entity->name,
                // scope only its own data
            ], 200);
        }

        // -----------------------------------------------
        // 3️⃣ INVALID LOGIN
        // -----------------------------------------------
        return response()->json([
            'status' => 'error',
            'message' => 'Invalid credentials',
        ], 401);
    }

    // ----------------------------------------------------
    // LOGOUT
    // ----------------------------------------------------
    public function logout(Request $request)
    {
        $request->user()->tokens()->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Logged out successfully',
        ]);
    }
}
