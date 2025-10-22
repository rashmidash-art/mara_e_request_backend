<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Department;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\Rule;

class SupplierController extends Controller
{
    /**
     * Display all suppliers.
     */
    public function index()
    {
        try {
            $suppliers = Supplier::all();

            // Transform suppliers to include category & department details
            $suppliers = $suppliers->map(function ($supplier) {
                // Category details
                $categoryIds = $supplier->categorei_id ? explode(',', $supplier->categorei_id) : [];
                $categories = Category::whereIn('id', $categoryIds)->get(['id', 'name']);

                // Department details
                $departmentIds = $supplier->department_id ? explode(',', $supplier->department_id) : [];
                $departments = Department::whereIn('id', $departmentIds)->get(['id', 'name']);

                // Append readable info
                $supplier->categories = $categories;
                $supplier->departments = $departments;

                return $supplier;
            });

            return response()->json([
                'status' => 'success',
                'message' => 'Suppliers retrieved successfully',
                'data' => $suppliers
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve suppliers',
                'error' => $e->getMessage()
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
            'contact_persion_name' => 'required|string|max:255',
            'address' => 'required|string',
            'tax_id' => 'required|string|max:100',
            'regi_no' => 'required|string|max:100',
            'category_id' => 'required|string|max:255',    // CSV string from API
            'department_id' => 'required|string|max:255',  // CSV string from API
            'regi_certificate' => 'nullable|string|max:255',
            'tax_certificate' => 'nullable|string|max:255',
            'insurance_certificate' => 'nullable|string|max:255',
            'status' => ['required', Rule::in(['Active', 'Suspended', 'Bad Rating', 'Inactive'])],
            'bc_status' => 'nullable|string|max:255',
            'compliance' => 'nullable|string|max:255',
        ]);

        // Map API fields to DB columns
        $validated['categorei_id'] = $validated['category_id'];  // important!
        unset($validated['category_id']);  // remove API key

        $validated['department_id'] = $validated['department_id']; // keep same
        // if your DB column is department_id, no need to change

        try {
            $supplier = Supplier::create($validated);

            return response()->json([
                'status' => 'success',
                'message' => 'Supplier created successfully',
                'data' => $supplier
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create supplier',
                'error' => $e->getMessage()
            ], 500);
        }
    }




    /**
     * Display a specific supplier.
     */
    public function show(string $id)
    {
        try {
            $supplier = Supplier::findOrFail($id);

            // Category details
            $categoryIds = $supplier->categorei_id ? explode(',', $supplier->categorei_id) : [];
            $categories = \App\Models\Category::whereIn('id', $categoryIds)->get(['id', 'name']);

            // Department details
            $departmentIds = $supplier->department_id ? explode(',', $supplier->department_id) : [];
            $departments = \App\Models\Department::whereIn('id', $departmentIds)->get(['id', 'name']);

            // Attach related info
            $supplier->categories = $categories;
            $supplier->departments = $departments;

            return response()->json([
                'status' => 'success',
                'message' => 'Supplier retrieved successfully',
                'data' => $supplier
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Supplier not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Something went wrong',
                'error' => $e->getMessage()
            ], 500);
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
            'tax_id' => 'sometimes|required|string|max:100',
            'regi_no' => 'sometimes|required|string|max:100',
            'category_id' => 'sometimes|required|string|max:255',    // CSV string from API
            'department_id' => 'sometimes|required|string|max:255',  // CSV string from API
            'regi_certificate' => 'nullable|string|max:255',
            'tax_certificate' => 'nullable|string|max:255',
            'insurance_certificate' => 'nullable|string|max:255',
            'status' => ['sometimes', 'required', Rule::in(['Active', 'Suspended', 'Bad Rating', 'Inactive'])],
            'bc_status' => 'nullable|string|max:255',
            'compliance' => 'nullable|string|max:255',
        ]);

        try {
            $supplier = Supplier::findOrFail($id);

            // Map API field to DB column
            if (isset($validated['category_id'])) {
                $validated['categorei_id'] = $validated['category_id'];
                unset($validated['category_id']);
            }

            if (isset($validated['department_id'])) {
                $validated['department_id'] = $validated['department_id'];
            }

            $supplier->update($validated);

            return response()->json([
                'status' => 'success',
                'message' => 'Supplier updated successfully',
                'data' => $supplier
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Supplier not found',
                 'error' => $e->getMessage()
            ], 401);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update supplier',
                'error' => $e->getMessage()
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
                'message' => 'Supplier deleted successfully'
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Supplier not found'
            ], 404);
        } catch (QueryException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete supplier',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
