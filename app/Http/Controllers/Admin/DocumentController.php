<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Document;
use App\Models\FileFormat;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;

class DocumentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {

        Log::info('DocumentController@index called');

        try {
            $documents = Document::all();

            if ($documents->isEmpty()) {
                Log::warning('No documents found in index()');

                return response()->json([
                    'status' => 'error',
                    'message' => 'No documents found'
                ], 401);
            }

            $documents->transform(function ($doc) {
                // Convert comma-separated IDs into arrays
                $categoryIds = $doc->categories ? explode(',', $doc->categories) : [];
                $fileFormatIds = $doc->file_formats ? explode(',', $doc->file_formats) : [];

                $categoryNames = DB::table('categories')
                    ->whereIn('id', $categoryIds)
                    ->pluck('name')
                    ->toArray();

                $fileFormatNames = DB::table('file_formats')
                    ->whereIn('id', $fileFormatIds)
                    ->pluck('name')
                    ->toArray();

                $doc->categories = implode(', ', $categoryNames);
                $doc->file_formats = implode(', ', $fileFormatNames);

                return $doc;
            });
            Log::info('Documents retrieved successfully');


            return response()->json([
                'status' => 'success',
                'message' => 'All documents retrieved successfully',
                'data' => $documents
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error in index(): ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
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
        Log::info('DocumentController@store called', $request->all());

        try {
            $request->validate([
                'name'             => 'required|string|max:255',
                'entiti_id'        => 'nullable|integer',
                'workflow_id'      => 'required|integer|exists:work_flows,id',
                'roles'            => 'nullable',
                'file_formats'     => 'nullable',
                'categories'       => 'nullable',
                'max_count'        => 'required|integer',
                'expiry_days'      => 'required|integer',
                'description'      => 'required|string',
                'status'           => 'required|string|max:255',
                'is_mandatory'     => 'required|integer',
                'is_enable'        => 'required|integer',
            ]);

            Log::info('Validation passed for store()');


            // Normalize stringified values (from frontend)
            $roles = is_array($request->roles)
                ? $request->roles
                : (is_string($request->roles) ? array_filter(explode(',', $request->roles)) : []);

            $fileFormatIds = [];
            if (!empty($request->file_formats)) {
                $fileFormatIds = FileFormat::whereIn('name', $request->file_formats)->pluck('id')->toArray();
            }
            $categories = is_array($request->categories)
                ? $request->categories
                : (is_string($request->categories) ? array_filter(explode(',', $request->categories)) : []);

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

            $document = Document::create([
                'name'             => $request->name,
                'entiti_id'        => $request->entiti_id,
                'workflow_id'      => $request->workflow_id,
                'work_flow_steps'  => implode(',', $steps),
                'roles'            => implode(',', $roles),
                'file_formats' => $fileFormatIds ? implode(',', $fileFormatIds) : null,
                'categories'       => implode(',', $categories),
                'max_count'        => $request->max_count,
                'expiry_days'      => $request->expiry_days,
                'description'      => $request->description,
                'status'           => $request->status,
                'is_mandatory'     => $request->is_mandatory,
                'is_enable'        => $request->is_enable,
            ]);
            Log::info('Document created successfully', ['document_id' => $document->id]);

            return response()->json([
                'status' => 'success',
                'message' => 'Document created successfully',
                'data' => $document,
            ], 201);
        } catch (ValidationException $e) {
            Log::warning('Validation failed in store()', $e->errors());

            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error in store(): ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create document',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function show($id)
    {
        Log::info("DocumentController@show called with ID: $id");

        try {
            $document = Document::find($id);

            if (!$document) {
                Log::warning("Document not found: ID $id");

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
            Log::info("Document retrieved successfully", ['id' => $id]);

            return response()->json([
                'status' => 'success',
                'message' => 'Document retrieved successfully',
                'data' => $document,
            ], 200);
        } catch (\Exception $e) {
            Log::error("Error in show($id): " . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch document',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        Log::info("DocumentController@update called", ['id' => $id, 'data' => $request->all()]);

        try {
            $document = Document::find($id);

            if (!$document) {
                Log::warning("Document not found for update: ID $id");

                return response()->json([
                    'status' => 'error',
                    'message' => 'Document not found',
                ], 404);
            }

            $request->validate([
                'name'             => 'required|string|max:255|unique:documents,name,' . $id,
                'entiti_id'        => 'nullable|integer',
                'workflow_id'      => 'required|integer|exists:work_flows,id',
                'roles'            => 'nullable',
                'file_formats'     => 'nullable',
                'categories'       => 'nullable',
                'max_count'        => 'required|integer',
                'expiry_days'      => 'required|integer',
                'description'      => 'required|string',
                'status'           => 'required|string|max:255',
                'is_mandatory'     => 'required|integer',
                'is_enable'        => 'required|integer',
            ]);
            Log::info("Validation passed for update($id)");

            $roles = is_array($request->roles)
                ? $request->roles
                : (is_string($request->roles) ? array_filter(explode(',', $request->roles)) : []);

            $fileFormatIds = [];
            if (!empty($request->file_formats)) {
                $fileFormatIds = FileFormat::whereIn('name', $request->file_formats)->pluck('id')->toArray();
            }
            $categories = is_array($request->categories)
                ? $request->categories
                : (is_string($request->categories) ? array_filter(explode(',', $request->categories)) : []);

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
                'roles'            => implode(',', $roles),
                'file_formats' => $fileFormatIds ? implode(',', $fileFormatIds) : null,
                'categories'       => implode(',', $categories),
                'max_count'        => $request->max_count,
                'expiry_days'      => $request->expiry_days,
                'description'      => $request->description,
                'status'           => $request->status,
                'is_mandatory'     => $request->is_mandatory,
                'is_enable'        => $request->is_enable,
            ]);
            Log::info("Document updated successfully", ['id' => $id]);

            return response()->json([
                'status' => 'success',
                'message' => 'Document updated successfully',
                'data' => $document,
            ], 200);
        } catch (ValidationException $e) {
            Log::warning("Validation failed in update($id)", $e->errors());

            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error("Error in update($id): " . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
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
        Log::info("DocumentController@destroy called with ID: $id");

        try {
            $document = Document::find($id);

            if (!$document) {

                Log::warning("Document not found for deletion: ID $id");

                return response()->json([
                    'status' => 'error',
                    'message' => 'Document not found',
                ], 404);
            }

            $document->delete();
            Log::info("Document deleted successfully", ['id' => $id]);

            return response()->json([
                'status' => 'success',
                'message' => 'Document deleted successfully',
            ], 200);
        } catch (\Exception $e) {
            Log::error("Error in destroy($id): " . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete document',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


    // public function getDocumentsByCategore($id)
    // {
    //     $documents = Document::whereRaw('FIND_IN_SET(?, categories)', [$id])->get();

    //     return response()->json([
    //         'status' => 'success',
    //         'data' => $documents
    //     ]);
    // }


    public function getDocumentsByCategore($id)
    {
        Log::info("DocumentController@getDocumentsByCategore called with category: $id");

        $documents = Document::whereRaw('FIND_IN_SET(?, categories)', [$id])->get();

        $documents = $documents->map(function ($doc) {
            // Convert comma-separated IDs into arrays
            $fileFormatIds = explode(',', $doc->file_formats);
            $categoryIds = explode(',', $doc->categories);

            // Fetch related names
            $fileFormats = FileFormat::whereIn('id', $fileFormatIds)->pluck('name')->toArray();
            $categories = Category::whereIn('id', $categoryIds)->pluck('name')->toArray();

            return [
                'id' => $doc->id,
                'name' => $doc->name,
                'entiti_id' => $doc->entiti_id,
                'workflow_id' => $doc->workflow_id,
                'work_flow_steps' => $doc->work_flow_steps,
                'roles' => $doc->roles,
                'file_formats' => $fileFormats,
                'categories' => $categories,
                'max_count' => $doc->max_count,
                'expiry_days' => $doc->expiry_days,
                'description' => $doc->description,
                'status' => $doc->status,
                'is_mandatory' => $doc->is_mandatory,
                'is_enable' => $doc->is_enable,
                'created_at' => $doc->created_at,
                'updated_at' => $doc->updated_at,
            ];
        });
        Log::info("Documents retrieved for category: $id");


        return response()->json([
            'status' => 'success',
            'data' => $documents
        ]);
    }
}
