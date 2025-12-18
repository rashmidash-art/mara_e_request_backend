<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\RequestType;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CategoreController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        try {
            $categories = Category::all();

            return response()->json([
                'status' => 'success',
                'message' => 'Categories retrieved successfully',
                'data' => $categories,
            ], 200);
        } catch (QueryException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve categories',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:categories,name',
            'description' => 'nullable|string',
            'status' => ['required', Rule::in([0, 1])],
        ]);

        try {
            $category = Category::create([
                'name' => $request->name,
                'description' => $request->description,
                'status' => $request->status,
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Category created successfully',
                'data' => $category,
            ], 200);
        } catch (QueryException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create category',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        try {
            $category = Category::findOrFail($id);

            return response()->json([
                'status' => 'success',
                'message' => 'Category retrieved successfully',
                'data' => $category,
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Category not found',
            ], 401);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $request->validate([
            'name' => 'sometimes|required|string|max:255|unique:categories,name,'.$id,
            'description' => 'nullable|string',
            'status' => ['sometimes', 'required', Rule::in([0, 1])],
        ]);

        try {
            $category = Category::findOrFail($id);

            $category->update($request->only(['name', 'description', 'status']));

            return response()->json([
                'status' => 'success',
                'message' => 'Category updated successfully',
                'data' => $category,
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Category not found',
            ], 401);
        } catch (QueryException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update category',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            $category = Category::findOrFail($id);
            $category->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Category deleted successfully',
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Category not found',
            ], 401);
        } catch (QueryException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete category',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function getRequestTypeByCat($id)
    {
        $getRequestType = RequestType::where('categori_id', $id)
            ->where('status', 1)
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $getRequestType,
        ]);
    }
}
