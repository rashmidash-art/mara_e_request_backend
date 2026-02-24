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
    private const HELD_STATUSES = ['submitted', 'in_approval'];

    private const UTILIZED_STATUSES = ['approved', 'po_created', 'delivery_completed', 'payment_completed', 'supplier_rating', 'closed'];

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

    // At the top of your controller class

    public function budgetSummary(Request $request)
    {
        $request->validate([
            'entity_id' => 'required|exists:entitis,id',
            'department_id' => 'nullable|exists:departments,id',
            'user_id' => 'nullable|exists:users,id',
        ]);

        $entityId = $request->entity_id;
        $departmentId = $request->department_id;
        $userId = $request->user_id;

        /** ============================================================
         *  HELPER: For utilized requests, use PO amount if exists,
         *          otherwise fall back to request amount
         * ============================================================ */
        $getUtilizedSum = function ($query) {
            // Join with po_upload_detalils to get PO amount where available
            return $query
                ->whereIn('status', self::UTILIZED_STATUSES)
                ->get()
                ->sum(function ($req) {
                    $poAmount = \App\Models\PoUploadDetalils::where('request_id', $req->request_id)
                        ->value('po_amount');

                    return $poAmount !== null ? (float) $poAmount : (float) $req->amount;
                });
        };

        /** ============================================================
         *  ENTITY
         * ============================================================ */
        $entity = Entiti::findOrFail($entityId);

        $entityBaseQuery = ModelsRequest::where('entiti', $entityId);

        $entityUtilized = $getUtilizedSum(
            ModelsRequest::where('entiti', $entityId)
        );

        $entityHeld = (float) ModelsRequest::where('entiti', $entityId)
            ->whereIn('status', self::HELD_STATUSES)
            ->sum('amount');

        $entityTotal = (float) $entity->budget;
        $entityRemaining = max(0, $entityTotal - ($entityUtilized + $entityHeld));

        /** ============================================================
         *  DEPARTMENT
         * ============================================================ */
        $departmentBudget = null;

        if ($departmentId) {
            $department = Department::findOrFail($departmentId);

            $deptUtilized = $getUtilizedSum(
                ModelsRequest::where('entiti', $entityId)
                    ->where('department', $departmentId)
            );

            $deptHeld = (float) ModelsRequest::where('entiti', $entityId)
                ->where('department', $departmentId)
                ->whereIn('status', self::HELD_STATUSES)
                ->sum('amount');

            $deptTotal = (float) $department->budget;
            $deptRemaining = max(0, $deptTotal - ($deptUtilized + $deptHeld));

            // Breakdown: count requests per status bucket
            $deptRequestBreakdown = ModelsRequest::where('entiti', $entityId)
                ->where('department', $departmentId)
                ->whereNotIn('status', ['draft']) // exclude drafts
                ->selectRaw('status, COUNT(*) as count, SUM(amount) as total_amount')
                ->groupBy('status')
                ->get();

            $departmentBudget = [
                'name' => $department->name,
                'total' => $deptTotal,
                'utilized' => $deptUtilized,
                'held' => $deptHeld,
                'remaining' => $deptRemaining,
                'request_breakdown' => $deptRequestBreakdown->map(fn ($r) => [
                    'status' => $r->status,
                    'count' => $r->count,
                    'total_amount' => (float) $r->total_amount,
                    'bucket' => $this->getBucket($r->status),
                ]),
            ];
        }

        /** ============================================================
         *  USER (LOA-based)
         * ============================================================ */
        $userBudget = null;

        if ($userId) {
            $user = User::findOrFail($userId);

            $userUtilized = $getUtilizedSum(
                ModelsRequest::where('entiti', $entityId)
                    ->where('user', $userId)
            );

            $userHeld = (float) ModelsRequest::where('entiti', $entityId)
                ->where('user', $userId)
                ->whereIn('status', self::HELD_STATUSES)
                ->sum('amount');

            $userLoa = (float) $user->loa;
            $userRemaining = max(0, $userLoa - ($userUtilized + $userHeld));

            $userBudget = [
                'name' => $user->name,
                'loa' => $userLoa,
                'utilized' => $userUtilized,
                'held' => $userHeld,
                'remaining' => $userRemaining,
            ];
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'entity' => [
                    'name' => $entity->name,
                    'total' => $entityTotal,
                    'utilized' => $entityUtilized,
                    'held' => $entityHeld,
                    'remaining' => $entityRemaining,
                ],
                'department' => $departmentBudget,
                'user' => $userBudget,
            ],
           
        ]);
    }

    /** ============================================================
     *  HELPER: label which bucket a status belongs to
     * ============================================================ */
    private function getBucket(string $status): string
    {
        if (in_array($status, self::HELD_STATUSES)) {
            return 'held';
        }
        if (in_array($status, self::UTILIZED_STATUSES)) {
            return 'utilized';
        }

        return 'excluded'; // draft, withdraw, reject
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
