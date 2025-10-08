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
        // Retrieve all documents
        $documents = Document::all();

        // Check if documents exist
        if ($documents->isEmpty()) {
            return response()->json([
                'status' => 'error',
                'message' => 'No documents found'
            ], 404);
        }

        // Return success response with documents
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
        // Validate the incoming request
        $request->validate([
            'name' => 'required|string|max:255',
            'work_flow_ids' => 'nullable|array',
            'work_flow_ids.*' => 'integer', // Ensure each ID is an integer
            'role_ids' => 'nullable|array',
            'role_ids.*' => 'integer',
            'fileformat_ids' => 'nullable|array',
            'fileformat_ids.*' => 'integer',
            'categorie_ids' => 'nullable|array',
            'categorie_ids.*' => 'integer',
            'max_count' => 'required|integer',
            'expiry_days' => 'required|integer',
            'description' => 'required|string',
            'status' => 'required|integer',
            'is_mandatory' => 'required|integer',
            'is_enable' => 'required|integer',
        ]);

        // Check if a document with the same name already exists
        $existingDocument = Document::where('name', $request->name)->first();
        if ($existingDocument) {
            return response()->json([
                'status' => 'error',
                'message' => 'Document with this name already exists'
            ], 400);
        }

        // Create a new document record
        $document = new Document();
        $document->name = $request->name;
        $document->work_flow_ids = json_encode($request->work_flow_ids); // Store Work Flow IDs as JSON
        $document->role_ids = json_encode($request->role_ids); // Store Role IDs as JSON
        $document->fileformat_ids = json_encode($request->fileformat_ids); // Store File Format IDs as JSON
        $document->categorie_ids = json_encode($request->categorie_ids); // Store Category IDs as JSON
        $document->max_count = $request->max_count;
        $document->expiry_days = $request->expiry_days;
        $document->description = $request->description;
        $document->status = $request->status;
        $document->is_mandatory = $request->is_mandatory;
        $document->is_enable = $request->is_enable;
        $document->save();

        // Return success response
        return response()->json([
            'status' => 'success',
            'message' => 'Document created successfully',
            'data' => $document
        ], 201);
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
    public function update(Request $request, string $id)
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

        // Validate the incoming request
        $request->validate([
            'name' => 'required|string|max:255',
            'work_flow_ids' => 'nullable|array',
            'work_flow_ids.*' => 'integer',
            'role_ids' => 'nullable|array',
            'role_ids.*' => 'integer',
            'fileformat_ids' => 'nullable|array',
            'fileformat_ids.*' => 'integer',
            'categorie_ids' => 'nullable|array',
            'categorie_ids.*' => 'integer',
            'max_count' => 'required|integer',
            'expiry_days' => 'required|integer',
            'description' => 'required|string',
            'status' => 'required|integer',
            'is_mandatory' => 'required|integer',
            'is_enable' => 'required|integer',
        ]);

        // Update the document
        $document->update([
            'name' => $request->name,
            'work_flow_ids' => json_encode($request->work_flow_ids),
            'role_ids' => json_encode($request->role_ids),
            'fileformat_ids' => json_encode($request->fileformat_ids),
            'categorie_ids' => json_encode($request->categorie_ids),
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
            'message' => 'Document updated successfully',
            'data' => $document
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
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

        // Delete the document
        $document->delete();

        // Return success response
        return response()->json([
            'status' => 'success',
            'message' => 'Document deleted successfully'
        ], 200);
    }
}
