<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Notification;
use App\Models\Request as ModelsRequest;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class UserDashboardController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $user = Auth::user();

        if (! $user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized',
            ], 401);
        }

        $userDepartmentId = $user->department_id; // assuming users have department_id
        $entityId = $user->entiti_id;

        /** ============================================================
         *  REQUEST STATUS SUMMARY
         * ============================================================ */
        $statuses = ['created', 'submitted', 'in_approval', 'approve', 'closed'];
        $requestSummary = [];
        foreach ($statuses as $status) {
            $requestSummary[$status] = ModelsRequest::where('entiti', $entityId)
                ->where('department', $userDepartmentId)
                 ->where('user', $user->id)
                ->where('status', $status)
                ->count();
        }

        /** ============================================================
         *  USER DEPARTMENT BUDGET
         * ============================================================ */
        $department = Department::where('id', $userDepartmentId)->first();

        $deptRequests = ModelsRequest::where('entiti', $entityId)
            ->where('department', $userDepartmentId);

        $utilized = $deptRequests->sum('amount'); // sum of all request amounts
        $held = $deptRequests->whereIn('status', ['submitted', 'in_approval'])->sum('amount');
        $remaining = max(0, $department->budget - ($utilized + $held));

        $departmentBudget = [
            'id' => $department->id,
            'name' => $department->name,
            'allocated' => (float) $department->budget,
            'utilized' => (float) $utilized,
            'held' => (float) $held,
            'remaining' => (float) $remaining,
        ];

        /** ============================================================
         *  TOP 5 SUPPLIERS BY RATING (ENTITY LEVEL)
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
            ->where('suppliers.entiti_id', $entityId)
            ->groupBy('suppliers.id', 'suppliers.name')
            ->orderByDesc(DB::raw('AVG(supplier_ratings.rating)'))
            ->take(5)
            ->get()
            ->map(function ($supplier) {
                return [
                    'id' => $supplier->id,
                    'name' => $supplier->name,
                    'avg_rating' => (float) number_format($supplier->avg_rating, 1),
                    'total_orders' => (int) $supplier->total_orders,
                    'total_value' => (float) $supplier->total_value,
                ];
            });

        /** ============================================================
         *  RECENT ACTIVITIES ON USER'S REQUESTS (UNIQUE)
         * ============================================================ */
        $recentActivities = Notification::where('user_id', $user->id)
            ->where('reference_id', '!=', null)
            ->orderByDesc('created_at')
            ->get()
            ->unique('reference_id') // unique per request
            ->take(5)
            ->map(function ($notif) {
                return [
                    'reference_id' => $notif->reference_id,
                    'message' => $notif->message,
                    'created_at' => $notif->created_at,
                ];
            });

        return response()->json([
            'status' => 'success',
            'data' => [
                'request_summary' => $requestSummary,
                'department_budget' => $departmentBudget,
                'top_suppliers' => $topSuppliers,
                'recent_activities' => $recentActivities,
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
