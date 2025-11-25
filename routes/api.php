<?php

use App\Http\Controllers\SuperAdminController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// OTP Routes
Route::post('/request-otp/whatsapp', [App\Http\Controllers\OtpController::class, 'requestOtpWhatsApp']);
Route::post('/request-otp/sms', [App\Http\Controllers\OtpController::class, 'requestOtpSms']);
Route::post('/verify-otp', [App\Http\Controllers\OtpController::class, 'verifyOtp']);

// Webhook Routes (for WhatsApp API callbacks)
Route::post('/webhooks/whatsapp/callback', [App\Http\Controllers\OtpController::class, 'whatsappWebhook']);

// Authentication Routes
Route::post('/login', [App\Http\Controllers\AuthController::class, 'login']); // Email/Password login
Route::post('/login-otp', [App\Http\Controllers\AuthController::class, 'loginWithOtp']); // OTP login
Route::post('/register-otp', [App\Http\Controllers\AuthController::class, 'registerWithOtp']); // OTP register
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [App\Http\Controllers\AuthController::class, 'logout']);
    Route::get('/user', [App\Http\Controllers\AuthController::class, 'user']);
    Route::get('/users', [App\Http\Controllers\AuthController::class, 'getAllUsers']);
    Route::get('/departments', [App\Http\Controllers\AuthController::class, 'getAllDepartments']);
    Route::get('/categories', [App\Http\Controllers\AuthController::class, 'getAllCategories']);
    Route::get('/types', [App\Http\Controllers\AuthController::class, 'getAllTypes']);
    Route::get('/roles', [App\Http\Controllers\AuthController::class, 'getAllRoles']);
    Route::get('/permissions', [App\Http\Controllers\AuthController::class, 'getAllPermissions']);
});

// Super Admin Routes
Route::prefix('super-admin')->middleware(['auth:sanctum', 'super.admin'])->group(function () {
    Route::post('/create-department', [SuperAdminController::class, 'createDepartment']);
    Route::put('/update-department/{id}', [SuperAdminController::class, 'updateDepartment']);
    Route::post('/create-categorie', [SuperAdminController::class, 'createCategory']); // Note: keeping original spelling
    Route::put('/update-categorie/{id}', [SuperAdminController::class, 'updateCategory']);
    Route::post('/create-type', [SuperAdminController::class, 'createType']);
    Route::put('/update-type/{id}', [SuperAdminController::class, 'updateType']);
    Route::post('/create-permission', [SuperAdminController::class, 'createPermission']);
    Route::put('/update-permission/{id}', [SuperAdminController::class, 'updatePermission']);
    Route::delete('/delete-permission/{id}', [SuperAdminController::class, 'deletePermission']);
    Route::post('/create-role', [SuperAdminController::class, 'createRole']);
    Route::put('/update-role/{id}', [SuperAdminController::class, 'updateRole']);
    Route::delete('/delete-role/{id}', [SuperAdminController::class, 'deleteRole']);
    Route::put('/update-user/{id}', [SuperAdminController::class, 'updateUser']);
    Route::post('/assign-user-roles-permissions', [SuperAdminController::class, 'assignUserRolesPermissions']);
});

// File Upload Routes
Route::prefix('admin')->middleware(['auth:sanctum'])->group(function () {
    Route::post('/upload-file', [App\Http\Controllers\FileController::class, 'upload']);
    Route::post('/upload-file-url', [App\Http\Controllers\FileController::class, 'uploadFromUrl']);
    Route::get('/file/{id}', [App\Http\Controllers\FileController::class, 'getFile']);
});

// Admin Routes (Tasks)
Route::prefix('admin')->middleware(['auth:sanctum'])->group(function () {
    Route::post('/create-task', [App\Http\Controllers\Admin\TaskController::class, 'create']);
    Route::get('/task-counts', [App\Http\Controllers\Admin\TaskController::class, 'getTaskCountsByType']);
    Route::get('/task-counts-status', [App\Http\Controllers\Admin\TaskController::class, 'getTaskCountsByStatus']);
    Route::get('/tasks', [App\Http\Controllers\Admin\TaskController::class, 'index']);
    Route::put('/tasks/{id}', [App\Http\Controllers\Admin\TaskController::class, 'update']);
    Route::post('/tasks/{id}/refuse', [App\Http\Controllers\Admin\TaskController::class, 'refuse']);
    Route::get('/tasks/{id}/refuse-history', [App\Http\Controllers\Admin\TaskController::class, 'getRefuseHistory']);
});
