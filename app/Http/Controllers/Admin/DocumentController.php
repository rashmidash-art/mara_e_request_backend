<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DocumentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        try {
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
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch documents',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {

            $request->validate([
                'name'             => 'required|string|max:255',
                'entiti_id'        => 'nullable|integer',
                'workflow_id'      => 'required|integer|exists:workflows,id', // ✅ Send from frontend
                'roles'            => 'nullable|array',
                'file_formats'     => 'nullable|array',
                'categories'       => 'nullable|array',
                'max_count'        => 'required|integer',
                'expiry_days'      => 'required|integer',
                'description'      => 'required|string',
                'status'           => 'required|string|max:255',
                'is_mandatory'     => 'required|integer',
                'is_enable'        => 'required|integer',
            ]);

            // ✅ Fetch workflow steps according to the workflow_id received
            $steps = DB::table('workflow_steps')
                ->where('workflow_id', $request->workflow_id)
                ->pluck('id')
                ->toArray();

            if (empty($steps)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No steps found for the selected workflow'
                ], 401);
            }

            // ✅ Create document
            $document = Document::create([
                'name'             => $request->name,
                'entiti_id'        => $request->entiti_id,
                'workflow_id'      => $request->workflow_id,
                'work_flow_steps'  => implode(',', $steps),
                'roles'            => $request->roles ? implode(',', $request->roles) : null,
                'file_formats'     => $request->file_formats ? implode(',', $request->file_formats) : null,
                'categories'       => $request->categories ? implode(',', $request->categories) : null,
                'max_count'        => $request->max_count,
                'expiry_days'      => $request->expiry_days,
                'description'      => $request->description,
                'status'           => $request->status,
                'is_mandatory'     => $request->is_mandatory,
                'is_enable'        => $request->is_enable,
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Document created successfully',
                'data' => $document
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 401);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create document',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        try {
            $document = Document::find($id);

            if (!$document) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Document not found'
                ], 401);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Document retrieved successfully',
                'data' => $document
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch document',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        try {
            $document = Document::find($id);

            if (!$document) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Document not found'
                ], 401);
            }

            $request->validate([
                'name'             => 'required|string|max:255|unique:documents,name,' . $id,
                'entiti_id'        => 'nullable|integer',
                'workflow_id'      => 'required|integer|exists:workflows,id', // ✅ incoming from UI
                'roles'            => 'nullable|array',
                'file_formats'     => 'nullable|array',
                'categories'       => 'nullable|array',
                'max_count'        => 'required|integer',
                'expiry_days'      => 'required|integer',
                'description'      => 'required|string',
                'status'           => 'required|string|max:255',
                'is_mandatory'     => 'required|integer',
                'is_enable'        => 'required|integer',
            ]);

            // ✅ Fetch associated workflow steps based on given workflow_id
            $steps = DB::table('workflow_steps')
                ->where('workflow_id', $request->workflow_id)
                ->pluck('id')
                ->toArray();

            if (empty($steps)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No steps found for the selected workflow'
                ], 401);
            }

            // ✅ Update document
            $document->update([
                'name'             => $request->name,
                'entiti_id'        => $request->entiti_id,
                'workflow_id'      => $request->workflow_id,
                'work_flow_steps'  => implode(',', $steps), // ✅ auto-updated steps
                'roles'            => $request->roles ? implode(',', $request->roles) : null,
                'file_formats'     => $request->file_formats ? implode(',', $request->file_formats) : null,
                'categories'       => $request->categories ? implode(',', $request->categories) : null,
                'max_count'        => $request->max_count,
                'expiry_days'      => $request->expiry_days,
                'description'      => $request->description,
                'status'           => $request->status,
                'is_mandatory'     => $request->is_mandatory,
                'is_enable'        => $request->is_enable,
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Document updated successfully',
                'data' => $document
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 401);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update document',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        try {
            $document = Document::find($id);

            if (!$document) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Document not found'
                ], 401);
            }

            $document->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Document deleted successfully'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete document',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
