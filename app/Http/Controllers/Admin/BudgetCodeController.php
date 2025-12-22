<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BudgetCode;
use App\Models\Department;
use App\Models\Entiti;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BudgetCodeController extends Controller
{
    /**
     * Display all budget codes
     */
    public function index()
    {
        try {
            $budgetCodes = BudgetCode::with(['entity', 'department'])
                ->orderBy('id', 'desc')
                ->get();

            return response()->json([
                'status' => 'success',
                'data' => $budgetCodes,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch budget codes',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Preview auto-generated budget code
     */
    public function preview(Request $request)
    {
        $request->validate([
            'department_id' => 'required|exists:departments,id',
        ]);

        $department = Department::with('entity')->findOrFail($request->department_id);
        $entity = $department->entity;

        // Get last sequence
        $lastCode = BudgetCode::where('department_id', $department->id)
            ->orderBy('id', 'desc')
            ->first();

        $nextSequence = 1;
        if ($lastCode && preg_match('/(\d+)$/', $lastCode->budget_code, $m)) {
            $nextSequence = (int) $m[1] + 1;
        }

        $entityCode = strtoupper(substr(preg_replace('/\s+/', '', $entity->name), 0, 2));
        $deptCode = strtoupper(substr(preg_replace('/\s+/', '', $department->name), 0, 3));
        $sequence = str_pad($nextSequence, 3, '0', STR_PAD_LEFT);

        return response()->json([
            'status' => 'success',
            'data' => [
                'budget_code' => "{$entityCode}-{$deptCode}-{$sequence}",
            ],
        ]);
    }

    /**
     * Store budget code
     */
    public function store(Request $request)
    {
        $request->validate([
            'entity_id' => 'required|exists:entitis,id',
            'department_id' => 'required|exists:departments,id',
            'budget_limit' => 'required|numeric|min:1',
            'description' => 'nullable|string',
            'status' => 'required|in:0,1',
        ]);

        DB::beginTransaction();

        try {
            $entity = Entiti::findOrFail($request->entity_id);
            $department = Department::findOrFail($request->department_id);

            // Validate department belongs to entity
            if ($department->entiti_id != $entity->id) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Department does not belong to selected entity',
                ], 400);
            }

            // Department remaining budget
            $deptUsed = BudgetCode::where('department_id', $department->id)->sum('budget_limit');
            $deptRemaining = $department->budget - $deptUsed;

            if ($request->budget_limit > $deptRemaining) {
                return response()->json([
                    'status' => 'error',
                    'message' => "Exceeds department remaining budget ({$deptRemaining})",
                ], 422);
            }

            // Entity remaining budget
            $entityUsed = BudgetCode::where('entity_id', $entity->id)->sum('budget_limit');
            $entityRemaining = $entity->budget - $entityUsed;

            if ($request->budget_limit > $entityRemaining) {
                return response()->json([
                    'status' => 'error',
                    'message' => "Exceeds entity remaining budget ({$entityRemaining})",
                ], 422);
            }

            // Generate budget code
            $lastCode = BudgetCode::where('department_id', $department->id)
                ->orderBy('id', 'desc')
                ->first();

            $nextSequence = 1;
            if ($lastCode && preg_match('/(\d+)$/', $lastCode->budget_code, $m)) {
                $nextSequence = (int) $m[1] + 1;
            }

            $entityCode = strtoupper(substr(preg_replace('/\s+/', '', $entity->name), 0, 2));
            $deptCode = strtoupper(substr(preg_replace('/\s+/', '', $department->name), 0, 3));
            $sequence = str_pad($nextSequence, 3, '0', STR_PAD_LEFT);

            $budgetCode = BudgetCode::create([
                'entity_id' => $entity->id,
                'department_id' => $department->id,
                'budget_code' => "{$entityCode}-{$deptCode}-{$sequence}",
                'budget_limit' => $request->budget_limit,
                'description' => $request->description,
                'status' => $request->status,
            ]);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Budget code created successfully',
                'data' => $budgetCode,
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create budget code',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function nextBudgetCode(Request $request)
    {
        $entityId = $request->query('entity_id');
        $departmentId = $request->query('department_id');

        if (! $entityId || ! $departmentId) {
            return response()->json([
                'status' => 'error',
                'message' => 'entity_id and department_id are required',
            ], 422);
        }

        $entity = Entiti::find($entityId);
        $department = Department::find($departmentId);

        if (! $entity || ! $department) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid entity or department',
            ], 404);
        }

        $last = BudgetCode::where('department_id', $departmentId)
            ->orderBy('id', 'desc')
            ->value('budget_code');

        $next = 1;
        if ($last && preg_match('/(\d+)$/', $last, $m)) {
            $next = ((int) $m[1]) + 1;
        }

        $entityCode = strtoupper(substr(preg_replace('/\s+/', '', $entity->name), 0, 2));
        $deptCode = strtoupper(substr(preg_replace('/\s+/', '', $department->name), 0, 3));
        $sequence = str_pad($next, 3, '0', STR_PAD_LEFT);

        return response()->json([
            'status' => 'success',
            'data' => [
                'budget_code' => "{$entityCode}-{$deptCode}-{$sequence}",
            ],
        ]);
    }

    /**
     * Update budget code
     */
    public function update(Request $request, $id)
    {
        $budgetCode = BudgetCode::find($id);

        if (! $budgetCode) {
            return response()->json([
                'status' => 'error',
                'message' => 'Budget code not found',
            ], 404);
        }

        $request->validate([
            'entity_id' => 'required|exists:entitis,id',
            'department_id' => 'required|exists:departments,id',
            'budget_limit' => 'required|numeric|min:1',
            'description' => 'nullable|string',
            'status' => 'required|in:0,1',
        ]);

        DB::beginTransaction();

        try {
            $entity = Entiti::findOrFail($request->entity_id);
            $department = Department::findOrFail($request->department_id);

            // Department remaining (ignore current budget code)
            $deptUsed = BudgetCode::where('department_id', $department->id)
                ->where('id', '!=', $budgetCode->id)
                ->sum('budget_limit');

            $deptRemaining = $department->budget - $deptUsed;

            if ($request->budget_limit > $deptRemaining) {
                return response()->json([
                    'status' => 'error',
                    'message' => "Exceeds department remaining budget ({$deptRemaining})",
                ], 422);
            }

            // Entity remaining (ignore current)
            $entityUsed = BudgetCode::where('entity_id', $entity->id)
                ->where('id', '!=', $budgetCode->id)
                ->sum('budget_limit');

            $entityRemaining = $entity->budget - $entityUsed;

            if ($request->budget_limit > $entityRemaining) {
                return response()->json([
                    'status' => 'error',
                    'message' => "Exceeds entity remaining budget ({$entityRemaining})",
                ], 422);
            }

            $budgetCode->update([
                'entity_id' => $entity->id,
                'department_id' => $department->id,
                'budget_limit' => $request->budget_limit,
                'description' => $request->description,
                'status' => $request->status,
            ]);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Budget code updated successfully',
                'data' => $budgetCode,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update budget code',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete budget code
     */
    public function destroy($id)
    {
        $budgetCode = BudgetCode::find($id);

        if (! $budgetCode) {
            return response()->json([
                'status' => 'error',
                'message' => 'Budget code not found',
            ], 404);
        }

        $budgetCode->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Budget code deleted successfully',
        ]);
    }
}
