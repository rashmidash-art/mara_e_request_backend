<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Department;
use App\Models\Request as ModelsRequest;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SupplierController extends Controller
{
    /**
     * Display all suppliers.
     */
    public function index()
    {
        try {
            // Fetch all suppliers
            $suppliers = Supplier::all();

            // Transform suppliers
            $suppliers = $suppliers->map(function ($supplier) {
                // Categories & departments
                $categoryIds = $supplier->categories ? explode(',', $supplier->categories) : [];
                $categories = Category::whereIn('id', $categoryIds)->get(['id', 'name']);
                $departmentIds = $supplier->departments ? explode(',', $supplier->departments) : [];
                $departments = Department::whereIn('id', $departmentIds)->get(['id', 'name']);

                $supplier->categories_detail = $categories;
                $supplier->departments_detail = $departments;

                // Fetch requests for this supplier with ratings
                $requests = ModelsRequest::with('requestTypeData:id,name', 'categoryData:id,name', 'supplierRating')
                    ->where('supplier_id', $supplier->id)
                    ->get()
                    ->map(function ($r) {
                        return [
                            'request_id' => $r->request_id,
                            'title' => $r->requestTypeData->name ?? '—',
                            'category' => $r->categoryData->name ?? '—',
                            'amount' => $r->amount,
                            'status' => $r->status,
                            'user_rating' => $r->supplierRating->rating ?? null,
                            'comment' => $r->supplierRating->comment ?? null,
                        ];
                    });

                $supplier->requests = $requests;

                // Calculate average rating for this supplier
                $supplier->avg_rating = $requests->avg('user_rating');

                // Total requests
                $supplier->total_requests = $requests->count();

                // Completed ratings (requests that have a supplier rating)
                $supplier->completed_ratings = $requests->filter(fn ($r) => $r['user_rating'] !== null)->count();

                // Average rating across all rated requests
                $supplier->avg_rating = $requests->filter(fn ($r) => $r['user_rating'] !== null)
                    ->avg('user_rating')
                                                ? round($requests->filter(fn ($r) => $r['user_rating'] !== null)->avg('user_rating'), 1)
                                                : null;

                return $supplier;
            });

            return response()->json([
                'status' => 'success',
                'message' => 'Suppliers retrieved successfully',
                'data' => $suppliers,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve suppliers',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Store a new supplier.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'bc_code' => 'required|string|max:255|unique:suppliers,bc_code',
            'email' => 'required|email|unique:suppliers,email',
            'phone' => 'required|string|max:20',
            'contact_persion_name' => 'nullable|string|max:255',
            'address' => 'required|string',
            'tax_id' => 'nullable|string|max:100',
            'regi_no' => 'nullable|string|max:100',
            'entiti_id' => 'required|string|max:255',
            // CSV strings
            'categories' => 'required|string|max:255',
            'departments' => 'nullable|string|max:255',
            // Multi-file fields
            'regi_certificates.*' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
            'tax_certificates.*' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
            'insurance_certificates.*' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',

            'status' => 'nullable|string|max:255',
            'bc_status' => 'nullable|string|max:255',
            'compliance' => 'nullable|string|max:255',
        ]);

        // File uploads: save and store CSV of paths
        foreach (['regi_certificates', 'tax_certificates', 'insurance_certificates'] as $field) {
            if ($request->hasFile($field)) {
                $filenames = collect($request->file($field))
                    ->map(function ($file) use ($field) {
                        $filename = $file->getClientOriginalName(); //  Original name only
                        $file->storeAs("upload/{$field}", $filename, 'public');

                        return $filename;
                    })
                    ->toArray();

                $validated[$field] = implode(',', $filenames); //  Store only filenames
            }
        }
        try {
            $supplier = Supplier::create($validated);

            return response()->json([
                'status' => 'success',
                'message' => 'Supplier created successfully',
                'data' => $supplier,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create supplier',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display a specific supplier.
     */
    public function show($id)
    {
        try {
            $supplier = Supplier::findOrFail($id);

            // Categories
            $categoryIds = $supplier->categories ? explode(',', $supplier->categories) : [];
            $categories = Category::whereIn('id', $categoryIds)->get(['id', 'name']);

            // Departments
            $departmentIds = $supplier->departments ? explode(',', $supplier->departments) : [];
            $departments = Department::whereIn('id', $departmentIds)->get(['id', 'name']);

            $supplier->categories_detail = $categories;
            $supplier->departments_detail = $departments;

            // Requests + ratings
            $requests = ModelsRequest::with('requestTypeData:id,name', 'categoryData:id,name', 'supplierRating')
                ->where('supplier_id', $supplier->id)
                ->get()
                ->map(function ($r) {
                    return [
                        'request_id' => $r->request_id,
                        'title' => $r->requestTypeData->name ?? '—',
                        'category' => $r->categoryData->name ?? '—',
                        'amount' => $r->amount,
                        'status' => $r->status,
                        'user_rating' => $r->supplierRating->rating ?? null,
                        'comment' => $r->supplierRating->comment ?? null,
                        'created_at' => $r->created_at,
                    ];
                });

            $supplier->requests = $requests;

            // Stats
            $supplier->total_requests = $requests->count();

            $ratedRequests = $requests->filter(fn ($r) => $r['user_rating'] !== null);

            $supplier->completed_ratings = $ratedRequests->count();

            $supplier->avg_rating = $ratedRequests->count()
                ? round($ratedRequests->avg('user_rating'), 1)
                : null;

            return response()->json([
                'status' => 'success',
                'message' => 'Supplier retrieved successfully',
                'data' => $supplier,
            ], 200);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Supplier not found',
            ], 404);
        }
    }

    /**
     * Update supplier details.
     */
    public function update(Request $request, string $id)
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'bc_code' => 'sometimes|required|string|max:255',
            'email' => ['sometimes', 'required', 'email', Rule::unique('suppliers')->ignore($id)],
            'phone' => 'sometimes|required|string|max:20',
            'contact_persion_name' => 'sometimes|required|string|max:255',
            'address' => 'sometimes|required|string',
            'tax_id' => 'sometimes|nullable|string|max:100',
            'regi_no' => 'sometimes|required|string|max:100',
            'categories' => 'sometimes|required|string|max:255',
            'entiti_id' => 'sometimes|required|string|max:255',
            // 'departments' => 'sometimes|required|string|max:255',
            'regi_certificates.*' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
            'tax_certificates.*' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
            'insurance_certificates.*' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
            'status' => 'nullable|string|max:255',
            'bc_status' => 'nullable|string|max:255',
            'compliance' => 'nullable|string|max:255',
        ]);

        try {
            $supplier = Supplier::findOrFail($id);

            // Handle multiple file uploads
            foreach (['regi_certificates', 'tax_certificates', 'insurance_certificates'] as $field) {
                if ($request->hasFile($field)) {
                    $paths = [];
                    foreach ($request->file($field) as $file) {
                        $paths[] = $file->store("upload/$field", 'public');
                    }
                    $validated[$field] = implode(',', $paths);
                }
            }

            $supplier->update($validated);

            return response()->json([
                'status' => 'success',
                'message' => 'Supplier updated successfully',
                'data' => $supplier,
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Supplier not found',
                'error' => $e->getMessage(),
            ], 401);
        } catch (QueryException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update supplier',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete a supplier.
     */
    public function destroy(string $id)
    {
        try {
            $supplier = Supplier::findOrFail($id);
            $supplier->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Supplier deleted successfully',
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Supplier not found',
                'error' => $e->getMessage(),
            ], 401);
        } catch (QueryException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete supplier',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
