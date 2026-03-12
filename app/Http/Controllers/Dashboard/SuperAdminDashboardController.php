<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Entiti;
use App\Models\Request as ModelsRequest;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SuperAdminDashboardController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $totalEntities = Entiti::count();
        $totalRequests = ModelsRequest::count();
        $totalRequestValue = ModelsRequest::sum('amount');
        $totalSuppliers = Supplier::count();
        $recentEntities = Entiti::latest()
            ->take(10)
            ->get(['id', 'name', 'created_at']);
        $topSuppliers = Supplier::select(
            'suppliers.id',
            'suppliers.name',
            DB::raw('AVG(supplier_ratings.rating) as avg_rating'),
            DB::raw('COUNT(DISTINCT requests.id) as total_orders'),
            DB::raw('COALESCE(SUM(requests.amount),0) as total_value')
        )
            ->join('supplier_ratings', 'suppliers.id', '=', 'supplier_ratings.supplier_id') // changed to join
            ->leftJoin('requests', 'suppliers.id', '=', 'requests.supplier_id')
            ->groupBy('suppliers.id', 'suppliers.name')
            ->orderByDesc('avg_rating')
            ->take(10)
            ->get()
            ->map(function ($supplier) {
                return [
                    'id' => $supplier->id,
                    'name' => $supplier->name,
                    'avg_rating' => round($supplier->avg_rating, 1),
                    'total_orders' => (int) $supplier->total_orders,
                    'total_value' => (float) $supplier->total_value,
                ];
            });

        return response()->json([
            'status' => 'success',
            'data' => [
                'total_entities' => $totalEntities,
                'total_requests' => $totalRequests,
                'total_request_value' => $totalRequestValue,
                'total_suppliers' => $totalSuppliers,
                'recent_entities' => $recentEntities,
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
