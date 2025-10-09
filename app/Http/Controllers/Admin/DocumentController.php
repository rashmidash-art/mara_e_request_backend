<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Document;
use Illuminate\Http\Request;

class DocumentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $documents = Document::all();

        if ($documents->isEmpty()) {
            return response()->json([
                'status' => 'error',
                'message' => 'No documents found'
            ], 401);
        }
        return response()->json([
            'status' => 'success',
            'message' => 'All documents retrieved successfully',
            'data' => $documents
        ], 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // Validate incoming request data
        $request->validate([
            'name' => 'required|string|max:255',
            'work_flow_ids' => 'nullable|array',
            'role_ids' => 'nullable|array',
            'fileformat_ids' => 'nullable|array',
            'categorie_ids' => 'nullable|array',
            'max_count' => 'required|integer',
            'expiry_days' => 'required|integer',
            'description' => 'required|string',
            'status' => 'required|integer',
            'is_mandatory' => 'required|integer',
            'is_enable' => 'required|integer',
        ]);

        // Check if document with the same name already exists
        $existing = Document::where('name', $request->name)->first();
        if ($existing) {
            return response()->json([
                'status' => 'error',
                'message' => 'Document with this name already exists',
            ], 400);
        }

        // Implode arrays into comma-separated strings
        $document = Document::create([
            'name' => $request->name,
            'work_flow_id' => $request->has('work_flow_ids') ? implode(',', $request->work_flow_ids) : null,
            'role_id' => $request->has('role_ids') ? implode(',', $request->role_ids) : null,
            'fileformat_id' => $request->has('fileformat_ids') ? implode(',', $request->fileformat_ids) : null,
            'categorie_id' => $request->has('categorie_ids') ? implode(',', $request->categorie_ids) : null,
            'max_count' => $request->max_count,
            'expiry_days' => $request->expiry_days,
            'description' => $request->description,
            'status' => $request->status,
            'is_mandatory' => $request->is_mandatory,
            'is_enable' => $request->is_enable,
        ]);

        // Return success response
        return response()->json([
            'status' => 'success',
            'message' => 'Document created successfully',
            'data' => $document
        ], 200);
    }



    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        // Find the document by ID
        $document = Document::find($id);

        // Check if document exists
        if (!$document) {
            return response()->json([
                'status' => 'error',
                'message' => 'Document not found'
            ], 404);
        }

        // Return success response with the document data
        return response()->json([
            'status' => 'success',
            'message' => 'Document retrieved successfully',
            'data' => $document
        ], 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $document = Document::find($id);

        if (!$document) {
            return response()->json([
                'status' => 'error',
                'message' => 'Document not found'
            ], 404);
        }

        $request->validate([
            'name' => 'required|string|max:255|unique:documents,name,' . $id,
            'work_flow_ids' => 'nullable|array',
            'role_ids' => 'nullable|array',
            'fileformat_ids' => 'nullable|array',
            'categorie_ids' => 'nullable|array',
            'max_count' => 'required|integer',
            'expiry_days' => 'required|integer',
            'description' => 'required|string',
            'status' => 'required|integer',
            'is_mandatory' => 'required|integer',
            'is_enable' => 'required|integer',
        ]);

        $document->update([
            'name' => $request->name,
            'work_flow_id' => isset($request->work_flow_ids) ? implode(',', $request->work_flow_ids) : null,
            'role_id' => isset($request->role_ids) ? implode(',', $request->role_ids) : null,
            'fileformat_id' => isset($request->fileformat_ids) ? implode(',', $request->fileformat_ids) : null,
            'categorie_id' => isset($request->categorie_ids) ? implode(',', $request->categorie_ids) : null,
            'max_count' => $request->max_count,
            'expiry_days' => $request->expiry_days,
            'description' => $request->description,
            'status' => $request->status,
            'is_mandatory' => $request->is_mandatory,
            'is_enable' => $request->is_enable,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Document updated successfully',
            'data' => $document
        ], 200);
    }


    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $document = Document::find($id);

        if (!$document) {
            return response()->json([
                'status' => 'error',
                'message' => 'Document not found'
            ], 404);
        }

        $document->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Document deleted successfully'
        ], 200);
    }
}
