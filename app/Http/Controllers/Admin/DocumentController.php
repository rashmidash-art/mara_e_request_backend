<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Document;
use App\Models\Entiti;
use App\Models\FileFormat;
use App\Models\RequestType;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class DocumentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {

        Log::info('DocumentController@index called');

        try {

            $user = $request->user();
            if ($user instanceof User && $user->user_type == 0) {
                $documents = Document::all();
            } elseif ($user instanceof Entiti) {
                $documents = Document::where('entiti_id', $user->id)->get();
            } elseif ($user instanceof User) {
                // If you want normal users to see all
                $documents = Document::all();
                // OR restrict by permissions if needed
                // $departments = Department::whereIn('id', $user->departments()->pluck('department_id'))->get();
            }
            // $documents = Document::all();

            if ($documents->isEmpty()) {
                Log::warning('No documents found in index()');

                return response()->json([
                    'status' => 'error',
                    'message' => 'No documents found',
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
                'data' => $documents,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error in index(): '.$e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch documents',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        Log::info('DocumentController@store called', ['data' => $request->all()]);

        try {
            // ---------------- VALIDATION ----------------
            $request->validate([
                'name' => 'required|string|max:255|unique:documents,name',
                'entiti_id' => 'nullable|integer',
                // 'workflow_id' => 'required|integer|exists:work_flows,id',
                'roles' => 'nullable',
                'file_formats' => 'required',
                'categories' => 'nullable',
                'request_types' => 'nullable',
                'max_count_type' => 'nullable|string',
                'max_count' => 'required|integer|min:1',
                'expiry_days' => 'required|integer|min:1',
                'description' => 'required|string',
                'status' => 'required|string|max:255',
                'is_mandatory' => 'required|integer|in:0,1',
                // 'is_enable' => 'required|integer|in:0,1',
            ]);

            Log::info('Validation passed for store');

            // ---------------- NORMALIZATION ----------------
            $normalize = fn ($val) => is_array($val)
                    ? array_values(array_filter($val))
                    : (is_string($val)
                        ? array_values(array_filter(explode(',', $val)))
                        : []);

            // $roles = $normalize($request->roles);
            $categories = $normalize($request->categories);
            $requestTypes = $normalize($request->request_types);
            $fileFormatIds = $normalize($request->file_formats);

            if (empty($fileFormatIds)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'At least one file format must be selected',
                ], 422);
            }

            // ---------------- WORKFLOW STEPS ----------------
            // $steps = DB::table('workflow_steps')
            //     ->where('workflow_id', $request->workflow_id)
            //     ->pluck('id')
            //     ->toArray();

            // if (empty($steps)) {
            //     return response()->json([
            //         'status' => 'error',
            //         'message' => 'No steps found for the selected workflow',
            //     ], 404);
            // }

            // ---------------- CREATE DOCUMENT ----------------
            $document = Document::create([
                'name' => $request->name,
                'entiti_id' => $request->entiti_id,
                'file_formats' => implode(',', $fileFormatIds),
                'categories' => implode(',', $categories),
                'request_types' => implode(',', $requestTypes),
                'max_count' => $request->max_count,
                'max_count_type' => $request->max_count_type,
                'expiry_days' => $request->expiry_days,
                'description' => $request->description,
                'status' => $request->status,
                'is_mandatory' => $request->is_mandatory,
                // 'is_enable' => $request->is_enable,
            ]);

            Log::info('Document created successfully', ['id' => $document->id]);

            return response()->json([
                'status' => 'success',
                'message' => 'Document created successfully',
                'data' => $document,
            ], 201);

        } catch (ValidationException $e) {
            Log::warning('Validation failed in store', $e->errors());

            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);

        } catch (\Exception $e) {
            Log::error('Error in store()', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
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
        $document = Document::find($id);

        if (! $document) {
            return response()->json([
                'status' => 'error',
                'message' => 'Document not found',
            ], 404);
        }

        $document->file_formats = $document->file_formats
            ? explode(',', $document->file_formats)
            : [];

        $document->categories = $document->categories
            ? explode(',', $document->categories)
            : [];

        $document->request_types = $document->request_types
            ? explode(',', $document->request_types)
            : [];

        $document->roles = $document->roles
            ? explode(',', $document->roles)
            : [];

        $document->work_flow_steps = $document->work_flow_steps
            ? explode(',', $document->work_flow_steps)
            : [];

        return response()->json([
            'status' => 'success',
            'data' => $document,
        ]);
    }

    public function update(Request $request, $id)
    {
        Log::info('DocumentController@update called', ['id' => $id, 'data' => $request->all()]);

        try {
            $document = Document::find($id);

            if (! $document) {
                Log::warning("Document not found for update: ID $id");

                return response()->json([
                    'status' => 'error',
                    'message' => 'Document not found',
                ], 404);
            }

            // VALIDATION
            $request->validate([
                'name' => 'required|string|max:255|unique:documents,name,'.$id,
                'entiti_id' => 'nullable|integer',
                // 'workflow_id' => 'required|integer|exists:work_flows,id',
                // 'roles' => 'nullable',
                'file_formats' => 'nullable',
                'categories' => 'nullable',
                'request_types' => 'nullable',
                'max_count_type' => 'nullable',
                'max_count' => 'required|integer',
                'expiry_days' => 'required|integer',
                'description' => 'required|string',
                'status' => 'required|string|max:255',
                'is_mandatory' => 'required|integer',
                // 'is_enable' => 'required|integer',
            ]);

            Log::info("Validation passed for update($id)");

            // ---------- NORMALIZATION ----------
            $normalize = fn ($val) => is_array($val) ? $val : (is_string($val) ? array_filter(explode(',', $val)) : []);

            $roles = $normalize($request->roles);
            $categories = $normalize($request->categories);
            $request_types = $normalize($request->request_types);

            // file formats are sent as names → convert to IDs
            // $fileFormatIds = [];
            // if (! empty($request->file_formats)) {
            //     $formats = $normalize($request->file_formats);
            //     $fileFormatIds = FileFormat::whereIn('name', $formats)->pluck('id')->toArray();
            // }

            $fileFormatIds = $normalize($request->file_formats);
            if (empty($fileFormatIds)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'At least one file format must be selected',
                ], 422);
            }
            // Get workflow steps
            // $steps = DB::table('workflow_steps')
            //     ->where('workflow_id', $request->workflow_id)
            //     ->pluck('id')
            //     ->toArray();

            // if (empty($steps)) {
            //     return response()->json([
            //         'status' => 'error',
            //         'message' => 'No steps found for the selected workflow',
            //     ], 404);
            // }

            // ---------- UPDATE ----------
            $document->update([
                'name' => $request->name,
                'entiti_id' => $request->entiti_id,
                'workflow_id' => $request->workflow_id,
                // 'work_flow_steps' => implode(',', $steps),
                'roles' => implode(',', $roles),
                'file_formats' => implode(',', $fileFormatIds),
                // 'file_formats' => $fileFormatIds ? implode(',', $fileFormatIds) : null,
                'categories' => implode(',', $categories),
                'request_types' => implode(',', $request_types),
                'max_count' => $request->max_count,
                'max_count_type' => $request->max_count_type,
                'expiry_days' => $request->expiry_days,
                'description' => $request->description,
                'status' => $request->status,
                'is_mandatory' => $request->is_mandatory,
                // 'is_enable' => $request->is_enable,
            ]);

            Log::info('Document updated successfully', ['id' => $id]);

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
            Log::error("Error in update($id): ".$e->getMessage(), [
                'trace' => $e->getTraceAsString(),
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

            if (! $document) {

                Log::warning("Document not found for deletion: ID $id");

                return response()->json([
                    'status' => 'error',
                    'message' => 'Document not found',
                ], 404);
            }

            $document->delete();
            Log::info('Document deleted successfully', ['id' => $id]);

            return response()->json([
                'status' => 'success',
                'message' => 'Document deleted successfully',
            ], 200);
        } catch (\Exception $e) {
            Log::error("Error in destroy($id): ".$e->getMessage(), [
                'trace' => $e->getTraceAsString(),
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
                // 'workflow_id' => $doc->workflow_id,
                // 'work_flow_steps' => $doc->work_flow_steps,
                // 'roles' => $doc->roles,
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
            'data' => $documents,
        ]);
    }

    public function getDocumentsByRequestType($id)
    {
        Log::info("DocumentController@getDocumentsByRequestType called with request_type: $id");

        $documents = Document::whereRaw('FIND_IN_SET(?, request_types)', [$id])->get();

        $documents = $documents->map(function ($doc) {

            $fileFormatIds = $doc->file_formats ? explode(',', $doc->file_formats) : [];
            $categoryIds = $doc->categories ? explode(',', $doc->categories) : [];
            $requestTypeIds = $doc->request_types ? explode(',', $doc->request_types) : [];
            $fileFormats = FileFormat::whereIn('id', $fileFormatIds)->pluck('name')->toArray();
            $categories = Category::whereIn('id', $categoryIds)->pluck('name')->toArray();
            $requestTypes = RequestType::whereIn('id', $requestTypeIds)->pluck('name')->toArray();

            return [
                'id' => $doc->id,
                'name' => $doc->name,
                'entiti_id' => $doc->entiti_id,
                // 'workflow_id' => $doc->workflow_id,
                // 'work_flow_steps' => $doc->work_flow_steps,
                // 'roles' => $doc->roles,
                'file_formats' => $fileFormats,
                'categories' => $categories,
                'request_types' => $requestTypes,
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

        Log::info("Documents retrieved for request_type: $id");

        return response()->json([
            'status' => 'success',
            'data' => $documents,
        ]);
    }
}
