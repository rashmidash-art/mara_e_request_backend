<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FileFormat;
use Illuminate\Http\Request;

class FileFormatController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $fileFormats = FileFormat::all();
        if ($fileFormats->isEmpty()) {
            return response()->json([
                'status' => 'error',
                'message' => 'No file formats found.'
            ], 401);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'All file formats retrieved successfully.',
            'data' => $fileFormats
        ], 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'name' => 'required|string|max:255|unique:file_formats,name',
            'description' => 'required|string|max:500',
            'status' => 'required|integer',
        ]);

        try {
            $fileFormat = FileFormat::create($validatedData);

            return response()->json([
                'status' => 'success',
                'message' => 'File format created successfully.',
                'data' => $fileFormat
            ], 200); // 201 for Created
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create file format. ' . $e->getMessage()
            ], 500); // 500 for Internal Server Error
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        // Find the file format by ID
        $fileFormat = FileFormat::find($id);

        if (!$fileFormat) {
            return response()->json([
                'status' => 'error',
                'message' => 'File format not found.'
            ], 401);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'File format retrieved successfully.',
            'data' => $fileFormat
        ], 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $validatedData = $request->validate([
            'name' => 'sometimes|required|string|max:255|unique:file_formats,name,' . $id, // Ignore the current record while checking for uniqueness
            'description' => 'sometimes|required|string|max:500',
            'status' => 'sometimes|required|integer',
        ]);

        $fileFormat = FileFormat::find($id);

        if (!$fileFormat) {
            return response()->json([
                'status' => 'error',
                'message' => 'File format not found.'
            ], 401);
        }

        try {
            $fileFormat->update($validatedData);

            return response()->json([
                'status' => 'success',
                'message' => 'File format updated successfully.',
                'data' => $fileFormat
            ], 200); // 200 for OK
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update file format. ' . $e->getMessage()
            ], 500); // 500 for Internal Server Error
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $fileFormat = FileFormat::find($id);
        if (!$fileFormat) {
            return response()->json([
                'status' => 'error',
                'message' => 'File format not found.'
            ], 401);
        }

        try {
            $fileFormat->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'File format deleted successfully.'
            ], 200); // 200 for OK
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete file format. ' . $e->getMessage()
            ], 500);
        }
    }
}
