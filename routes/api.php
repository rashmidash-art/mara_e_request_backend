<?php

use App\Http\Controllers\Admin\BudgetController;
use App\Http\Controllers\Admin\CategoreController;
use App\Http\Controllers\Admin\DocumentController;
use App\Http\Controllers\Admin\EntitiesController;
use App\Http\Controllers\Admin\FileFormatController;
use App\Http\Controllers\Admin\ManagerController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\RolePermissionController;
use App\Http\Controllers\Admin\SupplierController;
use App\Http\Controllers\Admin\WorkFlowController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetContrller;
use App\Http\Controllers\Admin\DeprtmentController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\WorkFlowTypeController;
// use App\Http\Controllers\CategoryController as ControllersCategoryController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;



Route::post('admin/login', [LoginController::class, 'login']);

Route::post('password/forgot', [PasswordResetContrller::class, 'sendResetLink']);
Route::post('password/reset', [PasswordResetContrller::class, 'reset']);


Route::middleware('auth:api,entiti-api')->group(function () {
    Route::post('admin/logout', [LoginController::class, 'logout']);
    Route::get('admin/user', function (Request $request) {
        return response()->json($request->user());
    });


    // -----------------------------
    // Roles Management (Dynamic CRUD)
    // Only accessible by Admin
    // -----------------------------


    Route::get('budgets', [BudgetController::class, 'index']);


    Route::middleware(['auth:api', 'permission'])->group(function () {
        Route::apiResource('roles', RoleController::class);
        Route::post('roles/assign', [RoleController::class, 'assignRole']);
        Route::post('roles/remove', [RoleController::class, 'removeRole']);




        // -----------------------------
        // 🔒 Role Permission Management
        // -----------------------------

        Route::get('allpermissions', [RolePermissionController::class, 'allpermissions']);
        Route::prefix('roles')->group(function () {

            Route::post('permissions/assign', [RolePermissionController::class, 'assignPermissions']);
            Route::post('permissions/remove', [RolePermissionController::class, 'removePermission']);
            Route::get('{role_id}/permissions', [RolePermissionController::class, 'getRolePermissions']);

            Route::post('permissions/manage', [RolePermissionController::class, 'manageRolePermissions']);
        });


        // Route::prefix('budgets')->group(function () {
        //     Route::get('/', [BudgetController::class, 'index']); // View all entity & department budgets
        //     Route::post('/allocate', [BudgetController::class, 'allocate']); // Allocate amount to department
        // });


        Route::post('budgets/allocate', [BudgetController::class, 'allocate']); // Admin only


        // -----------------------------
        // Master Modules
        // -----------------------------
        // For Entitis
        Route::apiResource('entities', EntitiesController::class);
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
        // routes/api.php
        Route::apiResource('supplier', SupplierController::class);
        Route::apiResource('fileformat', FileFormatController::class);
        Route::apiResource('document', DocumentController::class);
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
