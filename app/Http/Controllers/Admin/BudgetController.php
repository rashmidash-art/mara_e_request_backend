<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Entiti;
use App\Models\Request as ModelsRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class BudgetController extends Controller
{
    /**
     * View budgets (both Admin and Entity)
     */
    private const UTILIZED_STATUSES = ['approved', 'closed'];

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
        if (! $isSuperAdmin) {
            $entitiesQuery->where('id', $entityId);
        }
        $entities = $entitiesQuery->with(['departments' => function ($q) use ($isSuperAdmin, $entityId) {
            if (! $isSuperAdmin) {
                $q->where('entiti_id', $entityId);
            }
            $q->with(['users:id,name,loa,department_id']);
        }])->select('id', 'name', 'budget', 'description')->get();

        $data = $entities->map(function ($entity) {

            // ENTITY UTILIZED
            $entityUtilized = ModelsRequest::where('entiti', $entity->id)
                ->whereIn('status', self::UTILIZED_STATUSES)
                ->sum('amount');

            return [
                'entity_id' => $entity->id,
                'entity_name' => $entity->name,
                'entity_budget' => (float) $entity->budget,

                'entity_utilized' => $entityUtilized,
                'entity_remaining' => (float) $entity->budget - $entityUtilized,

                'departments' => $entity->departments->map(function ($d) use ($entity) {

                    $deptUtilized = ModelsRequest::where('entiti', $entity->id)
                        ->where('department', $d->id)
                        ->whereIn('status', self::UTILIZED_STATUSES)
                        ->sum('amount');

                    return [
                        'id' => $d->id,
                        'name' => $d->name,
                        'budget' => (float) $d->budget,

                        'department_utilized' => $deptUtilized,
                        'department_remaining' => (float) $d->budget - $deptUtilized,

                        'user_count' => $d->users->count(),

                        'users' => $d->users->map(function ($u) use ($entity, $d) {

                            $userUtilized = ModelsRequest::where('entiti', $entity->id)
                                ->where('department', $d->id)
                                ->where('user', $u->id)
                                ->whereIn('status', self::UTILIZED_STATUSES)
                                ->sum('amount');

                            return [
                                'id' => $u->id,
                                'name' => $u->name,

                                'loa' => (float) $u->loa,
                                'user_utilized' => $userUtilized,
                                'user_remaining' => (float) $u->loa - $userUtilized,
                            ];
                        }),
                    ];
                }),
            ];
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Entities with department budgets and user-wise LOA retrieved successfully',
            'data' => $data,
        ]);
    }


    public function budgetSummary(Request $request)
    {
        $request->validate([
            'entity_id'     => 'required|exists:entitis,id',
            'department_id' => 'nullable|exists:departments,id',
            'user_id'       => 'nullable|exists:users,id',
        ]);

        $entityId     = $request->entity_id;
        $departmentId = $request->department_id;
        $userId       = $request->user_id;

        /** ---------------- ENTITY ---------------- */
        $entity = Entiti::findOrFail($entityId);

        $entityUtilized = ModelsRequest::where('entiti', $entityId)
            ->whereIn('status', self::UTILIZED_STATUSES)
            ->sum('amount');

        /** ---------------- DEPARTMENT ---------------- */
        $departmentBudget = null;

        if ($departmentId) {
            $department = Department::findOrFail($departmentId);

            $deptUtilized = ModelsRequest::where('entiti', $entityId)
                ->where('department', $departmentId)
                ->whereIn('status', self::UTILIZED_STATUSES)
                ->sum('amount');

            $departmentBudget = [
                'total'     => (float) $department->budget,
                'utilized'  => (float) $deptUtilized,
                'remaining' => max(0, $department->budget - $deptUtilized),
            ];
        }

        /** ---------------- USER ---------------- */
        $userBudget = null;

        if ($userId) {
            $user = User::findOrFail($userId);

            $userUtilized = ModelsRequest::where('entiti', $entityId)
                ->where('user', $userId)
                ->whereIn('status', self::UTILIZED_STATUSES)
                ->sum('amount');

            $userBudget = [
                'loa'       => (float) $user->loa,
                'utilized'  => (float) $userUtilized,
                'remaining' => max(0, $user->loa - $userUtilized),
            ];
        }

        return response()->json([
            'status' => 'success',
            'data'   => [
                'entity' => [
                    'total'     => (float) $entity->budget,
                    'utilized'  => (float) $entityUtilized,
                    'remaining' => max(0, $entity->budget - $entityUtilized),
                ],
                'department' => $departmentBudget,
                'user'       => $userBudget,
            ],
        ]);
    }


    /**
     * Super Admin only: Allocate budget to department
     */
    public function allocate(Request $request)
    {
        $user = auth('api')->user();

        if (! isset($user->user_type) || $user->user_type !== 0) {
            return response()->json([
                'status' => 'error',
                'message' => 'Only Super Admin can allocate budgets',
            ], 403);
        }

        $validated = $request->validate([
            'department_id' => 'required|exists:departments,id',
            'amount' => 'required|numeric|min:0',
        ]);

        $department = Department::find($validated['department_id']);
        $entity = $department->entity;

        if (! $entity) {
            return response()->json([
                'status' => 'error',
                'message' => 'Entity not found for department',
            ], 404);
        }

        $totalAllocated = $entity->departments()->sum('budget');
        $available = $entity->budget - $totalAllocated;

        if ($validated['amount'] > $available + $department->budget) {
            return response()->json([
                'status' => 'error',
                'message' => 'Insufficient entity budget balance',
                'available' => $available,
            ], 400);
        }

        $department->update(['budget' => $validated['amount']]);

        return response()->json([
            'status' => 'success',
            'message' => 'Department budget updated successfully',
            'data' => $department,
        ]);
    }

    public function getLoaByUser($id)
    {
        $users = User::where('id', $id)->get();

        return response()->json([
            'status' => 'success',
            'data' => $users,
            'users' => $users,
        ]);
    }
}
