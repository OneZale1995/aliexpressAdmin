<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DictController;
use App\Http\Controllers\Api\ExportController;
use App\Http\Controllers\Api\FileController;
use App\Http\Controllers\Api\LoginLogController;
use App\Http\Controllers\Api\MenuController;
use App\Http\Controllers\Api\OperationLogController;
use App\Http\Controllers\Api\PermissionController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\SystemConfigController;
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
});
