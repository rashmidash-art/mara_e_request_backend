<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
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

            return response()->json([
                'status' => 'success',
                'message' => 'Suppliers retrieved successfully',
                'data' => $suppliers
            ], 200);
        } catch (QueryException $e) {
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
            'categorei_ids' => 'required|array|min:1',
            'categorei_ids.*' => 'integer|exists:categories,id',
            'department_ids' => 'required|array|min:1',
            'department_ids.*' => 'integer|exists:departments,id',
            'regi_certificate' => 'nullable|string|max:255',
            'tax_certificate' => 'nullable|string|max:255',
            'insurance_certificate' => 'nullable|string|max:255',
            'status' => ['required', Rule::in([0, 1, 2, 3])],
        ]);

        try {
            // Convert arrays to CSV
            $validated['categorei_id'] = implode(',', $validated['categorei_ids']);
            $validated['department_id'] = implode(',', $validated['department_ids']);

            // Remove the arrays to match model fillable
            unset($validated['categorei_ids'], $validated['department_ids']);

            $supplier = Supplier::create($validated);

            return response()->json([
                'status' => 'success',
                'message' => 'Supplier created successfully',
                'data' => $supplier
            ], 200);
        } catch (QueryException $e) {
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
            'categorei_ids' => 'sometimes|required|array|min:1',
            'categorei_ids.*' => 'integer|exists:categories,id',
            'department_ids' => 'sometimes|required|array|min:1',
            'department_ids.*' => 'integer|exists:departments,id',
            'regi_certificate' => 'nullable|string|max:255',
            'tax_certificate' => 'nullable|string|max:255',
            'insurance_certificate' => 'nullable|string|max:255',
            'status' => ['sometimes', 'required', Rule::in([0, 1, 2, 3])],
        ]);

        try {
            $supplier = Supplier::findOrFail($id);

            // Convert arrays to CSV if present
            if (isset($validated['categorei_ids'])) {
                $validated['categorei_id'] = implode(',', $validated['categorei_ids']);
                unset($validated['categorei_ids']);
            }
            if (isset($validated['department_ids'])) {
                $validated['department_id'] = implode(',', $validated['department_ids']);
                unset($validated['department_ids']);
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
                'message' => 'Supplier not found'
            ], 404);
        } catch (QueryException $e) {
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
