<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Entiti;
use App\Models\Department;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class BudgetController extends Controller
{
    /**
     * View budgets (both Admin and Entity)
     */
    public function index(Request $request)
    {
        $user = auth('api')->user() ?? auth('entiti-api')->user();

        $isSuperAdmin = false;
        $entityId = null;

        if (auth('api')->check()) {
            $isSuperAdmin = ((int) $user->user_type === 0);
            $entityId = $user->entiti_id;
        } elseif (auth('entiti-api')->check()) {
            $isSuperAdmin = false;
            $entityId = $user->id;
        }
        Log::info('Current user info', [
            'id' => $user->id,
            'email' => $user->email ?? null,
            'guard' => auth('api')->check() ? 'api' : 'entiti-api',
            'is_super_admin' => $isSuperAdmin,
            'entity_id' => $entityId,
        ]);

        $entitiesQuery = Entiti::query();
        if (!$isSuperAdmin) {
            $entitiesQuery->where('id', $entityId);
        }
        $entities = $entitiesQuery->with(['departments' => function ($q) use ($isSuperAdmin, $entityId) {
            if (!$isSuperAdmin) {
                $q->where('entiti_id', $entityId);
            }
            $q->with(['users:id,name,loa,department_id']);
        }])->select('id', 'name', 'budget', 'description')->get();

        $data = $entities->map(function ($entity) {
            return [
                'entity_id'        => $entity->id,
                'entity_name'      => $entity->name,
                'entity_budget'    => $entity->budget,
                'departments'      => $entity->departments->map(function ($d) {
                    $allocated = $d->users->sum('loa');
                    return [
                        'id'               => $d->id,
                        'name'             => $d->name,
                        'budget'           => $d->budget,
                        'user_count'       => $d->users->count(),
                        'allocated_budget' => $allocated,
                        'remaining_budget' => $d->budget - $allocated,
                        'users'            => $d->users->map(fn($u) => [
                            'id'   => $u->id,
                            'name' => $u->name,
                            'loa'  => $u->loa,
                        ]),
                    ];
                }),
            ];
        });

        return response()->json([
            'status'  => 'success',
            'message' => 'Entities with department budgets and user-wise LOA retrieved successfully',
            'data'    => $data,
        ]);
    }



    /**
     * Super Admin only: Allocate budget to department
     */
    public function allocate(Request $request)
    {
        $user = auth('api')->user();

        if (!isset($user->user_type) || $user->user_type !== 0) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Only Super Admin can allocate budgets',
            ], 403);
        }

        $validated = $request->validate([
            'department_id' => 'required|exists:departments,id',
            'amount'        => 'required|numeric|min:0',
        ]);

        $department = Department::find($validated['department_id']);
        $entity = $department->entity;

        if (!$entity) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Entity not found for department',
            ], 404);
        }

        $totalAllocated = $entity->departments()->sum('budget');
        $available = $entity->budget - $totalAllocated;

        if ($validated['amount'] > $available + $department->budget) {
            return response()->json([
                'status'    => 'error',
                'message'   => 'Insufficient entity budget balance',
                'available' => $available,
            ], 400);
        }

        $department->update(['budget' => $validated['amount']]);

        return response()->json([
            'status'  => 'success',
            'message' => 'Department budget updated successfully',
            'data'    => $department,
        ]);
    }



    public function getLoaByUser($id){
        $users = User::where('id', $id)->get();

        return response()->json([
            'status' => 'success',
            'data' => $users,
            'users' => $users
        ]);
    }
}
