<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Document;
use App\Models\Request as ModelsRequest;
use App\Models\RequestDocument;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CreateRequestController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        try {
            $data = ModelsRequest::orderByDesc('id')->get();

            return response()->json([
                'status' => 'success',
                'message' => 'Requests fetched successfully',
                'data' => $data
            ], 200);
        } catch (\Exception $e) {
            Log::error("Request Fetch Failed", ['error' => $e->getMessage()]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch requests',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /** Create a new request */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'request_type'   => 'nullable|integer',
                'category_id'    => 'nullable|integer',
                'department_id'  => 'nullable|integer',
                'behalf_of'      => 'nullable|in:0,1',
                'behalf_department_id' => 'required_if:behalf_of,1',

                'attachments'                => 'nullable|array',
                'attachments.*.document_id'  => 'required|integer',
                'attachments.*.file'         => 'required|file|max:10240',
            ]);

            $year = date('Y');
            $last = ModelsRequest::whereYear('created_at', $year)
                ->orderBy('id', 'desc')
                ->first();

            $nextNumber = $last ? $last->id + 1 : 1;
            $request_no = "REQ-{$year}-" . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);

            $requestMaster = ModelsRequest::create([
                'request_id'         => $request_no,       // stored in your DB column "request_id"
                'entiti'             => $request->entiti_id ?? null,
                'amount'             => $request->amount ?? null,
                'user'               => $request->user_id ?? null,
                'request_type'       => $request->requestType ?? null,
                'category'           => $request->category ?? null,
                'department'         =>  $request->department_id ?? null,
                'behalf_of'          =>  $request->requestOnBehalf ?? null,
                'description'           => $request->description ?? null,
                'supplier_id'           => $request->supplier ?? null,
                'expected_date'  => $request->date ?? null,
                'document_id' => $request->document_id ?? null,
                'business_justification'  => $request->business_justification ?? null,
                'behalf_of_department' =>  $request->onBehalfDepartment ?? null,
            ]);

            if (!empty($validated['attachments'])) {
                foreach ($validated['attachments'] as $doc) {

                    $file = $doc['file'];
                    $originalName = $file->getClientOriginalName();
                    $documentType = Document::find($doc['document_id']);
                    $folder = strtolower(str_replace(' ', '_', $documentType->name));
                    $newFileName = $requestMaster->id . '_' . $originalName;
                    $file->storeAs("upload/{$folder}", $newFileName, 'public');
                    RequestDocument::create([
                        'request_id'  => $requestMaster->id,
                        'document_id' => $doc['document_id'],
                        'document'    => $newFileName,
                    ]);
                }
            }


            return response()->json([
                'status' => 'success',
                'message' => 'Request created successfully',
                'data' => $requestMaster
            ], 201);
        } catch (\Exception $e) {

            Log::error("Request Store Error", ['error' => $e->getMessage()]);

            return response()->json([
                'status' => 'error',
                'message' => 'Something went wrong',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /** Show single request */
    public function show($id)
    {
        try {
            $req = ModelsRequest::find($id);

            if (!$req) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Request not found'
                ], 404);
            }

            $documents = RequestDocument::where('request_id', $req->id)->get();

            return response()->json([
                'status' => 'success',
                'message' => 'Request details retrieved',
                'data' => [
                    'request' => $req,
                    'documents' => $documents
                ]
            ], 200);
        } catch (\Exception $e) {
            Log::error("Request Fetch Error", ['error' => $e->getMessage()]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch request',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /** Update request */
    public function update(Request $request, $id)
    {
        $req = ModelsRequest::find($id);

        if (!$req) {
            return response()->json([
                'status' => 'error',
                'message' => 'Request not found'
            ], 404);
        }

        // VALIDATION
        $validated = $request->validate([
            'entiti' => 'nullable',
            'user' => 'nullable|integer',
            'request_type' => 'nullable|integer',
            'category' => 'nullable|integer',
            'department' => 'nullable|integer',
            'amount' => 'nullable|string',
            'description' => 'nullable|string',
            'supplier_id' => 'nullable|integer',
            'expected_date' => 'nullable|string',
            'priority' => 'nullable|string',
            'behalf_of' => 'nullable|integer|in:0,1',
            'behalf_of_department' => 'nullable|integer',
            'business_justification' => 'nullable|string',
            'status' => 'nullable|in:submitted,draft,deleted',

            'documents' => 'nullable|array',
            'documents.*.document_id' => 'required|integer',
            'documents.*.file' => 'required|file'
        ]);

        if ($request->behalf_of == 1 && empty($request->behalf_of_department)) {
            return response()->json([
                'status' => 'error',
                'message' => 'behalf_of_department is required when behalf_of is 1'
            ], 422);
        }

        if ($request->behalf_of == 0) {
            $validated['behalf_of_department'] = null;
        }

        DB::beginTransaction();

        try {
            // Update request
            $req->update($validated);

            // Replace documents only if provided
            if ($request->has('documents')) {

                RequestDocument::where('request_id', $req->id)->delete();

                foreach ($request->documents as $doc) {

                    $file = $doc['file'];
                    $originalName = $file->getClientOriginalName();

                    $file->storeAs("uploads/request_documents/", $originalName, 'public');

                    RequestDocument::create([
                        'request_id' => $req->id,
                        'document_id' => $doc['document_id'],
                        'documnet' => $originalName
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Request updated successfully',
                'data' => $req
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Request Update Failed", ['error' => $e->getMessage()]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update request',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /** Delete request */
    public function destroy($id)
    {
        try {
            $req = ModelsRequest::find($id);

            if (!$req) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Request not found'
                ], 404);
            }

            RequestDocument::where('request_id', $req->id)->delete();
            $req->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Request deleted successfully'
            ], 200);
        } catch (\Exception $e) {
            Log::error("Request Delete Failed", ['error' => $e->getMessage()]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete request',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
