<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EntityRequest;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EntityRequestTypeController extends Controller
{
    /**
     * Display a listing of all entity requests.
     */
    public function index()
    {
        $types = EntityRequest::all();

        return response()->json([
            'status' => 'success',
            'data' => $types
        ]);
    }

    /**
     * Store multiple entity request types in the database.
     * Each request_type_id will create a new record.
     */
    public function store(Request $request)
    {
        $request->validate([
            'entity_id' => 'required|integer|exists:entities,id',
            'request_type_ids' => 'required|array|min:1',
            'request_type_ids.*' => 'integer|exists:entity_request_types,id',
        ]);

        $entityId = $request->input('entity_id');
        $requestTypeIds = $request->input('request_type_ids');

        $createdRecords = [];

        foreach ($requestTypeIds as $typeId) {
            $record = EntityRequest::create([
                'entity_id' => $entityId,
                'request_type_id' => $typeId,
            ]);

            $createdRecords[] = $record;
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Request types saved successfully',
            'data' => $createdRecords
        ]);
    }

    /**
     * Display the specified entity request.
     */
    public function show($id)
    {
        $type = EntityRequest::find($id);

        if (!$type) {
            return response()->json([
                'status' => 'error',
                'message' => 'Entity request type not found'
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $type
        ]);
    }

    /**
     * Update the specified entity request.
     */
    public function update(Request $request, $id)
    {
        $type = EntityRequest::find($id);

        if (!$type) {
            return response()->json([
                'status' => 'error',
                'message' => 'Entity request type not found'
            ], 404);
        }

        $request->validate([
            'entity_id' => 'required|integer|exists:entities,id',
            'request_type_id' => 'required|integer|exists:entity_request_types,id',
        ]);

        $type->update([
            'entity_id' => $request->input('entity_id'),
            'request_type_id' => $request->input('request_type_id'),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Entity request type updated successfully',
            'data' => $type
        ]);
    }

    /**
     * Remove the specified entity request from storage.
     */
    public function destroy($id)
    {
        $type = EntityRequest::find($id);

        if (!$type) {
            return response()->json([
                'status' => 'error',
                'message' => 'Entity request type not found'
            ], 404);
        }

        $type->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Entity request type deleted successfully'
        ]);
    }
}
