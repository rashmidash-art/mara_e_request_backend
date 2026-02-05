<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\RequestType;
use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class RequestTypeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        try {
            $request_types = RequestType::orderBy('id', 'desc')->get();

            return response()->json(
                [
                    'status' => 'success',
                    'message' => 'Request Type retrieved successfully',
                    'data' => $request_types,
                ]
            );
        } catch (QueryException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve Request Type',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            $request->validate(
                [
                    'categori_id' => 'required|integer|exists:categories,id',
                    'request_code' => 'required|string|max:255|unique:request_types,request_code',
                    'name' => 'required|string|max:255|unique:request_types,name',
                    'descripton' => 'nullable|string',
                    'status' => 'required|string|max:255',
                ],
                [
                    'categori_id.required' => 'Category ID is required.',
                    'categori_id.exists' => 'Selected category does not exist.',
                    'request_code.unique' => 'Request code already exists.',
                    'name.unique' => 'Request name already exists.',
                ]
            );

            $reqest_type = RequestType::create([
                'categori_id' => $request->categori_id,
                'request_code' => $request->request_code,
                'name' => $request->name,
                'descripton' => $request->descripton,
                'status' => $request->status,
                'loa_validation' => $request->loa_validation,
                'administrative_request' => $request->administrative_request,
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Request Type created successfully',
                'data' => $reqest_type,
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->errors(),
            ], 401);
        } catch (QueryException $e) {

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve Request Type',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        try {
            $reqest_type = RequestType::find($id);
            if (! $reqest_type) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Request Type not found',
                ], 401);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Request type details retrieved successfully',
                'data' => $reqest_type,
            ], 200);
        } catch (QueryException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve Request type details',
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
            $requestType = RequestType::find($id);

            if (! $requestType) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Request type not found',
                ], 404);
            }

            $validated = $request->validate([
                'categori_id' => 'sometimes|required|integer|exists:categories,id',
                'request_code' => 'sometimes|required|string|max:255|unique:request_types,request_code,'.$requestType->id,
                'name' => 'sometimes|required|string|max:255|unique:request_types,name,'.$requestType->id,
                'descripton' => 'nullable|string',
                'status' => 'sometimes|required|string|max:255',
                'loa_validation' => 'nullable|string|max:255',
                'administrative_request' => 'nullable|string|max:255',
            ]);

            $requestType->update($validated);

            return response()->json([
                'status' => 'success',
                'message' => 'Request type updated successfully',
                'data' => $requestType,
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        try {
            $requestType = RequestType::find($id);
            if (! $requestType) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Request type not found',
                ], 401);
            }
            $requestType->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Request type deleted successfully',
            ], 200);
        } catch (QueryException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete request type',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
