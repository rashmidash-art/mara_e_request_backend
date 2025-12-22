<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EntityRequest;
use Illuminate\Http\Request;

class EntityRequestTypeController extends Controller
{
    /**
     * Display a listing of all entity requests.
     */
    public function index(Request $request)
    {
        $query = EntityRequest::with(['entity', 'category', 'requestType']);

        if ($request->has('entity_id') && $request->entity_id) {
            $query->where('entity_id', $request->entity_id);
        }

        if ($request->has('categore_id') && $request->categore_id) {
            $query->where('categore_id', $request->categore_id);
        }

        $data = $query->get();

        return response()->json([
            'status' => 'success',
            'data' => $data,
        ]);
    }

    /**
     * Store or update entity request links.
     * This prevents duplicate entries for the same entity & category.
     */
    public function store(Request $request)
    {
        $request->validate([
            'entity_id' => 'required|exists:entitis,id',
            'categore_id' => 'required|exists:categories,id',
            'request_type_ids' => 'required|array|min:1',
            'request_type_ids.*' => 'exists:request_types,id',
        ]);

        $entity_id = $request->entity_id;
        $categore_id = $request->categore_id;
        $request_type_ids = $request->request_type_ids;

        // Delete old links that are not in the new selection
        EntityRequest::where('entity_id', $entity_id)
            ->where('categore_id', $categore_id)
            ->whereNotIn('request_type_id', $request_type_ids)
            ->delete();

        // Create new links if they don’t exist
        foreach ($request_type_ids as $typeId) {
            EntityRequest::firstOrCreate([
                'entity_id' => $entity_id,
                'categore_id' => $categore_id,
                'request_type_id' => $typeId,
            ]);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Entity request links saved successfully',
        ]);
    }

    /**
     * Display the specified entity request.
     */
    public function show($id)
    {
        $type = EntityRequest::find($id);

        if (! $type) {
            return response()->json([
                'status' => 'error',
                'message' => 'Entity request type not found',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $type,
        ]);
    }

    /**
     * Update the specified entity request (multi-request types).
     */
    public function update(Request $request, $id = null)
    {
        // Same validation as store
        $request->validate([
            'entity_id' => 'required|exists:entitis,id',
            'categore_id' => 'required|exists:categories,id',
            'request_type_ids' => 'required|array|min:1',
            'request_type_ids.*' => 'exists:request_types,id',
        ]);

        $entity_id = $request->entity_id;
        $categore_id = $request->categore_id;
        $request_type_ids = $request->request_type_ids;

        // Delete old links that are not in the new selection
        EntityRequest::where('entity_id', $entity_id)
            ->where('categore_id', $categore_id)
            ->whereNotIn('request_type_id', $request_type_ids)
            ->delete();

        // Create new links if they don’t exist
        foreach ($request_type_ids as $typeId) {
            EntityRequest::firstOrCreate([
                'entity_id' => $entity_id,
                'categore_id' => $categore_id,
                'request_type_id' => $typeId,
            ]);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Entity request links updated successfully',
        ]);
    }

    /**
     * Remove the specified entity request from storage.
     */
    public function destroy($id)
    {
        $type = EntityRequest::find($id);

        if (! $type) {
            return response()->json([
                'status' => 'error',
                'message' => 'Entity request type not found',
            ], 404);
        }

        $type->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Entity request type deleted successfully',
        ]);
    }

    public function groupDelete(Request $request)
    {
        $request->validate([
            'entity_id' => 'required|exists:entitis,id',
            'categore_id' => 'required|exists:categories,id',
        ]);

        EntityRequest::where('entity_id', $request->entity_id)
            ->where('categore_id', $request->categore_id)
            ->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'All request types for this entity and category deleted successfully',
        ]);
    }
}
