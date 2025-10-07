<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Entiti;
use Illuminate\Http\Request;

class EntitiesController extends Controller
{
    /**
     * Display a listing of the resource.
     */

    // public function __construct()
    // {
    //     $this->middleware(['auth', 'role:admin']);
    // }

    public function index()
    {
        $entities = Entiti::all();
        return response()->json(['status' => 'success', 'data' => $entities]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            // add your entity-specific validation rules here
        ]);

        $entity = Entiti::create(
            [
                'name' => $request->name,
                'company_code' => $request->company_code,
                'bc_dimention_value' => $request->bc_dimention_value,
                'description' => $request->description,
                'budget' => $request->budget ?? 0,
                'status' => $request->status ?? 0
            ]
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Entity created successfully',
            'data' => $entity,
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $entity = Entiti::findOrFail($id);

        return response()->json(['status' => 'success', 'data' => $entity]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $entity = Entiti::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            // your update rules here
        ]);

        $entity->update([
            'name' => $request->name,
            'company_code' => $request->company_code,
            'bc_dimention_value' => $request->bc_dimention_value,
            'description' => $request->description,
            'budget' => $request->budget ?? 0,
            'status' => $request->status ?? 0
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Entity updated successfully',
            'data' => $entity,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $entity = Entiti::findOrFail($id);
        $entity->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Entity deleted successfully',
        ]);
    }
}
