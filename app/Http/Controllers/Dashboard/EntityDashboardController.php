<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Entiti;
use App\Models\PoUploadDetalils;
use App\Models\Request as ModelsRequest;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class EntityDashboardController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        // $entityId = Auth::user()->entiti_id; // logged-in entity

        // $entity = Entiti::findOrFail($entityId);

        $user = Auth::guard('entiti-api')->user() ?? Auth::guard('api')->user();

        if (! $user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized',
            ], 401);
        }

        /*
        |--------------------------------------------------------------------------
        | Determine entity id
        |--------------------------------------------------------------------------
        */

        if ($user instanceof Entiti) {
            $entityId = $user->id; // entity login
        } else {
            $entityId = $user->entiti_id; // user login
        }

        $entity = Entiti::find($entityId);

        if (! $entity) {
            return response()->json([
                'status' => 'error',
                'message' => 'Entity not found',
            ], 404);
        }

        /** ============================================================
         *  HELPER: Utilized sum using PO amount if exists
         * ============================================================ */
        $getUtilizedSum = function ($query) {
            return $query->get()->sum(function ($req) {
                $poAmount = PoUploadDetalils::where('request_id', $req->request_id)->value('po_amount');

                return $poAmount !== null ? (float) $poAmount : (float) $req->amount;
            });
        };

        /** ============================================================
         *  REQUEST STATUS SUMMARY
         * ============================================================ */
        $statuses = [
            'draft', 'submitted', 'in_approval', 'approved',
            'po_created', 'delivery_completed', 'payment_completed', 'closed',
        ];

        $requestSummary = [];
        foreach ($statuses as $status) {
            $requestSummary[$status] = ModelsRequest::where('entiti', $entityId)
                ->where('status', $status)
                ->count();
        }

        /** ============================================================
         *  DEPARTMENT-WISE BUDGET ALLOCATION
         * ============================================================ */
        $departments = Department::where('entiti_id', $entityId)->get();

        $departmentBudget = $departments->map(function ($dept) use ($getUtilizedSum, $entityId) {
            $deptRequests = ModelsRequest::where('entiti', $entityId)
                ->where('department', $dept->id);

            $utilized = $getUtilizedSum($deptRequests);
            $held = (float) $deptRequests->whereIn('status', ['submitted', 'in_approval'])->sum('amount');
            $remaining = max(0, (float) $dept->budget - ($utilized + $held));

            return [
                'id' => $dept->id,
                'name' => $dept->name,
                'allocated' => (float) $dept->budget,
                'utilized' => $utilized,
                'held' => $held,
                'remaining' => $remaining,
            ];
        });

        /** ============================================================
         *  ENTITY BUDGET SUMMARY
         * ============================================================ */
        $entityTotal = (float) $entity->budget; // entity LOA
        $totalHeld = $departmentBudget->sum('held');
        $totalUtilized = $departmentBudget->sum('utilized');
        $totalRemaining = max(0, $entityTotal - ($totalHeld + $totalUtilized));

        $entityBudget = [
            'total' => $entityTotal,
            'held' => $totalHeld,
            'utilized' => $totalUtilized,
            'remaining' => $totalRemaining,
        ];

        /** ============================================================
         *  RECENT 5 REQUESTS
         * ============================================================ */
        $recentRequests = ModelsRequest::where('entiti', $entityId)
            ->latest('created_at')
            ->take(5)
            ->with(['requestTypeData', 'userData']) // eager load relations
            ->get()
            ->map(function ($req) {
                return [
                    'request_id' => $req->request_id,
                    'amount' => $req->amount,
                    'expected_date' => $req->expected_date,
                    'status' => $req->getFinalStatus()['final_status'],
                    'request_type' => optional($req->requestTypeData)->name,
                    'user_name' => optional($req->userData)->name,
                ];
            });

        /** ============================================================
         *  RECENT ACTIVITIES
         * Latest action per request
         * ============================================================ */
        $recentActivities = ModelsRequest::where('entiti', $entityId)
            ->with('workflowDetails') // assumes you have workflowDetails relation
            ->get()
            ->map(function ($req) {
                $lastAction = $req->workflowDetails()->latest('updated_at')->first();

                return [
                    'request_id' => $req->request_id,
                    'status' => $req->status,
                    'last_action' => $lastAction?->action,
                    'updated_at' => $lastAction?->updated_at,
                ];
            })
            ->sortByDesc('updated_at')
            ->take(5)
            ->values();

        /** ============================================================
         *  TOP 5 SUPPLIERS BY RATING
         * ============================================================ */
        $topSuppliers = Supplier::select(
            'suppliers.id',
            'suppliers.name',
            DB::raw('AVG(supplier_ratings.rating) as avg_rating'),
            DB::raw('COUNT(DISTINCT requests.id) as total_orders'),
            DB::raw('COALESCE(SUM(requests.amount),0) as total_value')
        )
            ->join('supplier_ratings', 'suppliers.id', '=', 'supplier_ratings.supplier_id')
            ->leftJoin('requests', 'suppliers.id', '=', 'requests.supplier_id')
            ->where('suppliers.entiti_id', $entityId) // supplier must belong to login entity
            ->groupBy('suppliers.id', 'suppliers.name')
            ->orderByDesc(DB::raw('AVG(supplier_ratings.rating)')) // order by rating
            ->take(10)
            ->get()
            ->map(function ($supplier) {
                return [
                    'id' => $supplier->id,
                    'name' => $supplier->name,
                    'avg_rating' => (float) number_format($supplier->avg_rating, 1), // removes 5.000
                    'total_orders' => (int) $supplier->total_orders,
                    'total_value' => (float) $supplier->total_value,
                ];
            });

        return response()->json([
            'status' => 'success',
            'data' => [
                'request_summary' => $requestSummary,
                'department_budget' => $departmentBudget,
                'entity_budget' => $entityBudget,
                'recent_requests' => $recentRequests,
                'recent_activities' => $recentActivities,
                'top_suppliers' => $topSuppliers,
            ],
        ]);

    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
