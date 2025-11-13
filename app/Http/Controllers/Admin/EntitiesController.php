<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Entiti;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

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
            'email' => 'required|email|unique:entitis,email',
            'password' => 'nullable|string|min:6',
            'company_code' => 'nullable|string|max:50',
            'budget' => 'nullable|numeric',
            'description' => 'nullable|string',
            'status' => ['nullable', Rule::in([0, 1])]
        ]);

        $entity = Entiti::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => isset($validated['password']) ? Hash::make($validated['password']) : null,

            'company_code' => $validated['company_code'] ?? null,
            'budget' => $validated['budget'] ?? 0,
            'description' => $validated['description'] ?? null,
            'status' => $validated['status'] ?? 0,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Entity created successfully',
            'data' => $entity
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
    public function update(Request $request, string $id)
    {
        $entity = Entiti::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'email' => ['sometimes', 'required', 'email', Rule::unique('entitis', 'email')->ignore($entity->id)],
            'password' => 'nullable|string|min:6',
            'company_code' => 'nullable|string|max:50',
            'budget' => 'nullable|numeric',
            'description' => 'nullable|string',
            'status' => ['nullable', Rule::in([0, 1])]
        ]);

        $entity->update([
            'name' => $validated['name'] ?? $entity->name,
            'email' => $validated['email'] ?? $entity->email,
            'password' => isset($validated['password']) ? Hash::make($validated['password']) : $entity->password,
            'company_code' => $validated['company_code'] ?? $entity->company_code,
            'budget' => $validated['budget'] ?? $entity->budget,
            'description' => $validated['description'] ?? $entity->description,
            'status' => $validated['status'] ?? $entity->status,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Entity updated successfully',
            'data' => $entity->refresh()
        ], 200);
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


    public function getUserbyEntiti($id)
    {
        $users = User::where('entiti_id', $id)->get();

        return response()->json([
            'status' => 'success',
            'data' => $users,
            'users' => $users
        ]);
    }
}
