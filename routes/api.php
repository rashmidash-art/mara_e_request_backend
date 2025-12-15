<?php

// use App\Http\Controllers\CategoryController as ControllersCategoryController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::options('{any}', fn () => response()->json())->where('any', '.*');

use App\Http\Controllers\Admin\BudgetController;
use App\Http\Controllers\Admin\CategoreController;
use App\Http\Controllers\Admin\CreateRequestController;
use App\Http\Controllers\Admin\DeprtmentController;
use App\Http\Controllers\Admin\DocumentController;
use App\Http\Controllers\Admin\EntitiesController;
use App\Http\Controllers\Admin\EscalationController;
use App\Http\Controllers\Admin\FileFormatController;
use App\Http\Controllers\Admin\ManagerController;
use App\Http\Controllers\Admin\RequestTypeController;
use App\Http\Controllers\Admin\RequestWorkflowDetailsController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\RolePermissionController;
use App\Http\Controllers\Admin\SupplierController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\WorkFlow_RoleAssignController;
use App\Http\Controllers\Admin\WorkFlowController;
use App\Http\Controllers\Admin\WorkFlowStepsController;
use App\Http\Controllers\Admin\WorkFlowTypeController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetContrller;

Route::post('admin/login', [LoginController::class, 'login']);

Route::post('password/forgot', [PasswordResetContrller::class, 'sendResetLink']);
Route::post('password/reset', [PasswordResetContrller::class, 'reset']);

// Need to configure
Route::get('categore/{id}/document', action: [DocumentController::class, 'getDocumentsByCategore']);

Route::middleware('auth:api,entiti-api')->group(function () {
    Route::get('budgets', [BudgetController::class, 'index']);
});

Route::middleware('auth:api,entiti-api')->group(function () {

    Route::get('/requestDetails', [CreateRequestController::class, 'requestDetailsAll']);

    Route::post('admin/logout', [LoginController::class, 'logout']);
    Route::get('admin/user', function (Request $request) {
        return response()->json($request->user());

    });
    // -----------------------------
    // Roles Management (Dynamic CRUD)
    // Only accessible by Admin
    // -----------------------------

    Route::middleware(['auth:entiti-api'])->group(function () {
        Route::get('entity/itself', [EntitiesController::class, 'itself']);
    });

    Route::middleware(['auth:api'])->group(function () {
        Route::get('user/{id}/loa', action: [BudgetController::class, 'getLoaByUser']);

        // Route::get('/requestDetails', [CreateRequestController::class, 'requestDetailsAll']);

    });

    Route::middleware(['auth:api,entiti-api', 'permission'])->group(function () {
        Route::apiResource('entities', EntitiesController::class);
    });

    Route::middleware(['permission'])->group(function () {
        Route::apiResource('request', controller: CreateRequestController::class);

        Route::apiResource('requestWorkflow', controller: RequestWorkflowDetailsController::class);
        Route::post('/requests/action', [RequestWorkflowDetailsController::class, 'takeAction']);
    });
    Route::middleware(['auth:api,entiti-api', 'permission'])->group(function () {
        Route::apiResource('roles', RoleController::class);
        Route::post('roles/assign', [RoleController::class, 'assignRole']);
        Route::post('roles/remove', [RoleController::class, 'removeRole']);
        Route::get('roles/{id}/users', [RoleController::class, 'getUsersByRole']);
        // -----------------------------
        //  Role Permission Management
        // -----------------------------

        Route::get('allpermissions', [RolePermissionController::class, 'allpermissions']);
        Route::prefix('roles')->group(function () {

            Route::post('permissions/assign', [RolePermissionController::class, 'assignPermissions']);
            Route::post('permissions/remove', [RolePermissionController::class, 'removePermission']);
            Route::get('{role_id}/permissions', [RolePermissionController::class, 'getRolePermissions']);
            Route::post('permissions/manage', [RolePermissionController::class, 'manageRolePermissions']);
        });
        Route::post('budgets/allocate', [BudgetController::class, 'allocate']); // Admin only
        // -----------------------------
        // Master Modules
        // -----------------------------
        // For Entitis
        // Route::apiResource('entities', EntitiesController::class);
        Route::get('entities/{id}/users', action: [EntitiesController::class, 'getUserbyEntiti']);
        Route::apiResource('work-flows', WorkFlowTypeController::class);
        Route::apiResource('managers', ManagerController::class);
        // For Department
        Route::apiResource('department', DeprtmentController::class);
        Route::get('entities/{id}/departments', [DeprtmentController::class, 'getByEntity']);
        Route::get('department/{id}/users', action: [DeprtmentController::class, 'getUserbyDepartment']);
        // For Users
        Route::get('users/search', [UserController::class, 'search']);
        Route::get('users/next-employee-id', [UserController::class, 'nextEmployeeId']);
        Route::apiResource('users', UserController::class);
        Route::apiResource('categore', CategoreController::class);
        Route::get('/categories/{id}/request-types', [CategoreController::class, 'getRequestTypeByCat']);
        Route::apiResource('request_type', RequestTypeController::class);
        Route::apiResource('workflow', WorkFlowController::class);
        Route::apiResource('workflowsteps', WorkFlowStepsController::class);
        Route::get('workflow/{id}/steps', action: [WorkFlowStepsController::class, 'getStepByWorkflow']);
        Route::post('workflowsteps/reorder', [WorkFlowStepsController::class, 'reorder']);
        Route::apiResource('workflow-role/assign', WorkFlow_RoleAssignController::class);
        Route::get('/workflowbyTypeandCat', [WorkFlowController::class, 'getWorkflowByTypeAndCategory']);
        Route::apiResource('escalations', EscalationController::class);





        // routes/api.php
        Route::apiResource('supplier', controller: SupplierController::class);
        Route::apiResource('fileformat', FileFormatController::class);
        Route::apiResource('document', DocumentController::class);
        // Route::get('categore/{id}/document', action: [DocumentController::class, 'getDocumentsByCategore']);
        Route::apiResource('request', controller: CreateRequestController::class);
        // Route::get('/requestDetails', [CreateRequestController::class, 'requestDetailsAll']);
        Route::get('/requests/actionable', [
            CreateRequestController::class,
            'myActionableRequests',
        ]);

        Route::apiResource('requestWorkflow', controller: RequestWorkflowDetailsController::class);
        Route::post('/request-workflow/{request_id}/action', [RequestWorkflowDetailsController::class, 'takeAction']);
    });

    // Route::middleware(['auth', 'role:admin'])->group(function () {
    //     Route::apiResource('roles', RoleController::class);
    //     Route::post('roles/assign', [RoleController::class, 'assignRole']);
    //     Route::post('roles/remove', [RoleController::class, 'removeRole']);

    //     // Master Modules
    //     Route::apiResource('entities', EntitiesController::class);
    //     Route::apiResource('work-flows', WorkFlowController::class);
    //     Route::apiResource('managers', ManagerController::class);
    //     Route::apiResource('department', DeprtmentController::class);
    //     Route::apiResource('users', UserController::class);
    //     Route::apiResource('categore', CategoreController::class);
    //     Route::apiResource('supplier', SupplierController::class);
    // });

});
