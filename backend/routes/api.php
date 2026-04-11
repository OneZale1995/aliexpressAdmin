<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DictController;
use App\Http\Controllers\Api\ExportController;
use App\Http\Controllers\Api\FileController;
use App\Http\Controllers\Api\LoginLogController;
use App\Http\Controllers\Api\MenuController;
use App\Http\Controllers\Api\OperationLogController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PermissionController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\ShopController;
use App\Http\Controllers\Api\SystemConfigController;
use App\Http\Controllers\Api\TeamController;
use App\Http\Controllers\Api\TeamUserController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

// 公开接口
Route::post('/user/login', [AuthController::class, 'login']);

// 需要认证的接口
Route::middleware(['auth:sanctum', 'operation.log'])->group(function () {
    Route::post('/user/info', [AuthController::class, 'info']);
    Route::post('/user/logout', [AuthController::class, 'logout']);

    // 个人中心
    Route::post('/profile/update', [ProfileController::class, 'update']);
    Route::post('/profile/password', [ProfileController::class, 'updatePassword']);
    Route::post('/profile/avatar', [ProfileController::class, 'uploadAvatar']);

    // 用户管理
    Route::post('/users/list', [UserController::class, 'index']);
    Route::post('/users/create', [UserController::class, 'store']);
    Route::post('/users/detail', [UserController::class, 'show']);
    Route::post('/users/update', [UserController::class, 'update']);
    Route::post('/users/delete', [UserController::class, 'destroy']);

    // 角色管理
    Route::post('/roles/list', [RoleController::class, 'index']);
    Route::post('/roles/create', [RoleController::class, 'store']);
    Route::post('/roles/detail', [RoleController::class, 'show']);
    Route::post('/roles/update', [RoleController::class, 'update']);
    Route::post('/roles/delete', [RoleController::class, 'destroy']);

    // 权限管理
    Route::post('/permissions/list', [PermissionController::class, 'index']);
    Route::post('/permissions/create', [PermissionController::class, 'store']);
    Route::post('/permissions/detail', [PermissionController::class, 'show']);
    Route::post('/permissions/update', [PermissionController::class, 'update']);
    Route::post('/permissions/delete', [PermissionController::class, 'destroy']);

    // 菜单管理
    Route::post('/menus/list', [MenuController::class, 'index']);
    Route::post('/menus/create', [MenuController::class, 'store']);
    Route::post('/menus/detail', [MenuController::class, 'show']);
    Route::post('/menus/update', [MenuController::class, 'update']);
    Route::post('/menus/delete', [MenuController::class, 'destroy']);

    // 文件管理
    Route::post('/files/list', [FileController::class, 'index']);
    Route::post('/files/upload', [FileController::class, 'upload']);
    Route::post('/files/delete', [FileController::class, 'destroy']);

    // 系统配置
    Route::post('/system-configs/list', [SystemConfigController::class, 'index']);
    Route::post('/system-configs/create', [SystemConfigController::class, 'store']);
    Route::post('/system-configs/update', [SystemConfigController::class, 'update']);
    Route::post('/system-configs/delete', [SystemConfigController::class, 'destroy']);
    Route::post('/system-configs/batch', [SystemConfigController::class, 'batchSave']);

    // 数据字典
    Route::post('/dict-types/list', [DictController::class, 'typeIndex']);
    Route::post('/dict-types/create', [DictController::class, 'typeStore']);
    Route::post('/dict-types/update', [DictController::class, 'typeUpdate']);
    Route::post('/dict-types/delete', [DictController::class, 'typeDestroy']);
    Route::post('/dict-data/list', [DictController::class, 'dataIndex']);
    Route::post('/dict-data/create', [DictController::class, 'dataStore']);
    Route::post('/dict-data/update', [DictController::class, 'dataUpdate']);
    Route::post('/dict-data/delete', [DictController::class, 'dataDestroy']);
    Route::post('/dict/get', [DictController::class, 'getByCode']);

    // 操作日志
    Route::post('/operation-logs/list', [OperationLogController::class, 'index']);
    Route::post('/operation-logs/delete', [OperationLogController::class, 'destroy']);
    Route::post('/operation-logs/clear', [OperationLogController::class, 'clear']);

    // 登录日志
    Route::post('/login-logs/list', [LoginLogController::class, 'index']);
    Route::post('/login-logs/delete', [LoginLogController::class, 'destroy']);
    Route::post('/login-logs/clear', [LoginLogController::class, 'clear']);

    // 通用导出
    Route::post('/export', [ExportController::class, 'export']);

    // 团队管理
    Route::post('/teams/list', [TeamController::class, 'index']);
    Route::post('/teams/create', [TeamController::class, 'store']);
    Route::post('/teams/detail', [TeamController::class, 'show']);
    Route::post('/teams/update', [TeamController::class, 'update']);
    Route::post('/teams/delete', [TeamController::class, 'destroy']);

    // 团队用户管理（团队管理员添加/管理采购用户）
    Route::post('/team-users/list', [TeamUserController::class, 'index']);
    Route::post('/team-users/create', [TeamUserController::class, 'store']);
    Route::post('/team-users/update', [TeamUserController::class, 'update']);
    Route::post('/team-users/delete', [TeamUserController::class, 'destroy']);

    // 店铺管理
    Route::post('/shops/list', [ShopController::class, 'index']);
    Route::post('/shops/create', [ShopController::class, 'store']);
    Route::post('/shops/detail', [ShopController::class, 'show']);
    Route::post('/shops/update', [ShopController::class, 'update']);
    Route::post('/shops/delete', [ShopController::class, 'destroy']);

    // 订单管理
    Route::post('/orders/list', [OrderController::class, 'index']);
    Route::post('/orders/status-counts', [OrderController::class, 'statusCounts']);
    Route::post('/orders/sync', [OrderController::class, 'sync']);
    Route::post('/orders/sync-start', [OrderController::class, 'syncStart']);
    Route::post('/orders/sync-progress', [OrderController::class, 'syncProgress']);
    Route::post('/orders/update-comment', [OrderController::class, 'updateComment']);
    Route::post('/orders/update-backend-fields', [OrderController::class, 'updateBackendFields']);
    Route::post('/orders/export', [OrderController::class, 'export']);
    Route::post('/orders/ship', [OrderController::class, 'ship']);
    Route::post('/orders/label', [OrderController::class, 'printLabel']);

    // 中国邮政物流
    Route::post('/orders/chinapost/create', [OrderController::class, 'chinaPostCreateOrder']);
    Route::post('/orders/chinapost/label', [OrderController::class, 'chinaPostLabel']);
    Route::post('/orders/chinapost/cancel', [OrderController::class, 'chinaPostCancel']);

    // 雷翼/sz56t物流
    Route::post('/orders/sz56t/create', [OrderController::class, 'sz56tCreateOrder']);
    Route::post('/orders/sz56t/label', [OrderController::class, 'sz56tLabel']);
    Route::post('/orders/sz56t/mark-shipped', [OrderController::class, 'sz56tMarkShipped']);
    Route::post('/orders/sz56t/tracking-number', [OrderController::class, 'sz56tGetTrackingNumber']);
});
