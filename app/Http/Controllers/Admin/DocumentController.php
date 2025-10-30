<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Document;
use Illuminate\Http\Request;
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

            // Map IDs to names
            $documents->transform(function ($doc) {
                // Convert comma-separated IDs into arrays
                $categoryIds = $doc->categories ? explode(',', $doc->categories) : [];
                $fileFormatIds = $doc->file_formats ? explode(',', $doc->file_formats) : [];

                // Fetch related names from DB
                $categoryNames = DB::table('categories')
                    ->whereIn('id', $categoryIds)
                    ->pluck('name')
                    ->toArray();

                $fileFormatNames = DB::table('file_formats')
                    ->whereIn('id', $fileFormatIds)
                    ->pluck('name')
                    ->toArray();

                // Replace IDs with readable names
                $doc->categories = implode(', ', $categoryNames);
                $doc->file_formats = implode(', ', $fileFormatNames);

                return $doc;
            });

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
                'workflow_id'      => 'required|integer|exists:work_flows,id',
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

            // Fetch workflow steps
            $steps = DB::table('workflow_steps')
                ->where('workflow_id', $request->workflow_id)
                ->pluck('id')
                ->toArray();

            if (empty($steps)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No steps found for the selected workflow',
                ], 404);
            }

            // Create document
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
                'data' => $document,
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create document',
                'error' => $e->getMessage(),
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
                    'message' => 'Document not found',
                ], 404);
            }

            // Convert comma-separated fields to arrays
            $document->work_flow_steps = $document->work_flow_steps ? explode(',', $document->work_flow_steps) : [];
            $document->roles = $document->roles ? explode(',', $document->roles) : [];
            $document->file_formats = $document->file_formats ? explode(',', $document->file_formats) : [];
            $document->categories = $document->categories ? explode(',', $document->categories) : [];

            return response()->json([
                'status' => 'success',
                'message' => 'Document retrieved successfully',
                'data' => $document,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch document',
                'error' => $e->getMessage(),
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
                    'message' => 'Document not found',
                ], 404);
            }

            $request->validate([
                'name'             => 'required|string|max:255|unique:documents,name,' . $id,
                'entiti_id'        => 'nullable|integer',
                'workflow_id'      => 'required|integer|exists:work_flows,id',
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

            $steps = DB::table('workflow_steps')
                ->where('workflow_id', $request->workflow_id)
                ->pluck('id')
                ->toArray();

            if (empty($steps)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No steps found for the selected workflow',
                ], 404);
            }

            $document->update([
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
                'message' => 'Document updated successfully',
                'data' => $document,
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update document',
                'error' => $e->getMessage(),
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
                    'message' => 'Document not found',
                ], 404);
            }

            $document->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Document deleted successfully',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete document',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
