<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Middleware\RoleMiddleware;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\UserManagementController;
use App\Http\Controllers\Api\RoleManagementController;
use App\Http\Controllers\Api\PermissionManagementController;
use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\NotificationController;
// Route::get('/user', function (Request $request) {
//     return $request->user();
// })->middleware('auth:sanctum');


Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
Route::middleware(['auth:sanctum', 'role:Admin'])->group(function () {
    Route::get('/admin/dashboard', function () {
        return response()->json([
            'message' => 'Admin access granted.',
        ]);
    });
});
Route::get('/staff', [AuthController::class, 'staff'])->middleware(['auth:sanctum', 'role:Admin']);
Route::middleware('auth:sanctum')->group(function () {
        // Route::apiResource('customers', CustomerController::class);
        // Route::apiResource('products', ProductController::class);  
        // Route::apiResource('orders', OrderController::class);

         Route::apiResource('orders', OrderController::class)
        ->only(['index'])
        ->middleware('permission:orders.view_all,orders.view_assigned');

    Route::apiResource('orders', OrderController::class)
        ->only(['store'])
        ->middleware('permission:orders.create');

    Route::apiResource('orders', OrderController::class)
        ->only(['show'])
        ->middleware('permission:orders.view_all,orders.view_assigned');

    Route::apiResource('orders', OrderController::class)
        ->only(['update'])
        ->middleware('permission:orders.update');

    Route::apiResource('orders', OrderController::class)
        ->only(['destroy'])
        ->middleware('permission:orders.delete');


    // Customers
    Route::apiResource('customers', CustomerController::class)
        ->only(['index', 'show'])
        ->middleware('permission:customers.view');

    Route::apiResource('customers', CustomerController::class)
        ->only(['store'])
        ->middleware('permission:customers.create');

    Route::apiResource('customers', CustomerController::class)
        ->only(['update'])
        ->middleware('permission:customers.update');

    Route::apiResource('customers', CustomerController::class)
        ->only(['destroy'])
        ->middleware('permission:customers.delete');


    // Products
    Route::apiResource('products', ProductController::class)
        ->only(['index', 'show'])
        ->middleware('permission:products.view');

    Route::apiResource('products', ProductController::class)
        ->only(['store'])
        ->middleware('permission:products.create');

    Route::apiResource('products', ProductController::class)
        ->only(['update'])
        ->middleware('permission:products.update');

    Route::apiResource('products', ProductController::class)
        ->only(['destroy'])
        ->middleware('permission:products.delete');

        Route::patch('/orders/{order}/status', [OrderController::class, 'updateStatus'])->middleware('permission:orders.change_status');
        Route::patch('/orders/{order}/assign', [OrderController::class, 'assignOrder'])->middleware('permission:orders.assign');
        Route::get('/orders/{order}/history',[OrderController::class, 'showstatusHistory'])->middleware('permission:orders.view_all,orders.view_assigned');
        // order notes
        Route::post('/orders/{order}/notes', [OrderController::class, 'addNotes'])->middleware('permission:orders.add_notes');
        Route::get('/orders/{order}/notes', [OrderController::class, 'getNotes'])->middleware('permission:orders.view_all,orders.view_assigned');
        // order activity
        Route::get('/orders/{order}/activity',[OrderController::class, 'activity'])->middleware('permission:orders.view_all,orders.view_assigned');
        Route::get('/dashboard', [OrderController::class, 'dashboard']);
        Route::get('/user', [AuthController::class, 'user']);
        Route::post('/logout', [AuthController::class, 'logout']);

        // notification 
    Route::prefix('notifications')->group(function () {
        Route::get('/', [NotificationController::class, 'index']);
        Route::get('/unread-count', [NotificationController::class,'unreadCount',]);
        Route::patch('/{id}/read', [NotificationController::class,'markAsRead',]);
        Route::patch('/read-all', [NotificationController::class, 'markAllAsRead',]);
    });
});

Route::middleware('auth:sanctum')->prefix('admin')->group(function () {
        Route::get('/users/roles', [UserManagementController::class,'roles']);
        Route::get('/users', [UserManagementController::class,'index']);
        Route::post('/users', [UserManagementController::class,'store']);
        Route::put('/users/{user}', [UserManagementController::class,'update']);
         Route::get('/roles', [RoleManagementController::class, 'index']);
        Route::post('/roles', [RoleManagementController::class, 'store']);
        Route::put('/roles/{role}', [RoleManagementController::class, 'update']);
        Route::delete('/roles/{role}', [RoleManagementController::class, 'destroy']);
        Route::get('/permissions', [PermissionManagementController::class, 'index']);
        Route::get('/roles/{role}/permissions', [PermissionManagementController::class, 'showRolePermissions']);
        Route::put('/roles/{role}/permissions', [PermissionManagementController::class, 'updateRolePermissions']);
        Route::get('/audit-logs', [AuditLogController::class,'index']);
        
});