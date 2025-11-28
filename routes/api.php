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
Route::post('/user/login', [App\Http\Controllers\AuthController::class, 'loginWithUsername']); // Username/Password login
Route::post('/check/token', [App\Http\Controllers\AuthController::class, 'checkToken']); // Check token validity
Route::post('/login-otp', [App\Http\Controllers\AuthController::class, 'loginWithOtp']); // OTP login
Route::post('/register-otp', [App\Http\Controllers\AuthController::class, 'registerWithOtp']); // OTP register
Route::post('/set-password', [App\Http\Controllers\AuthController::class, 'setPassword']); // Set password and activate user
Route::post('/forgot-password', [App\Http\Controllers\AuthController::class, 'forgotPassword']); // Request OTP for password reset
Route::post('/reset-password', [App\Http\Controllers\AuthController::class, 'resetPassword']); // Reset password with OTP verification
Route::middleware(['auth:sanctum', 'user.active'])->group(function () {
    Route::post('/logout', [App\Http\Controllers\AuthController::class, 'logout']);
    Route::post('/update-password', [App\Http\Controllers\AuthController::class, 'updatePassword']);
    Route::get('/user', [App\Http\Controllers\AuthController::class, 'user']);
    Route::get('/users', [App\Http\Controllers\AuthController::class, 'getAllUsers']);
    Route::get('/departments', [App\Http\Controllers\AuthController::class, 'getAllDepartments']);
    Route::get('/categories', [App\Http\Controllers\AuthController::class, 'getAllCategories']);
    Route::get('/task-names', [App\Http\Controllers\AuthController::class, 'getAllTaskNames']);
    Route::get('/roles', [App\Http\Controllers\AuthController::class, 'getAllRoles']);
    Route::get('/permissions', [App\Http\Controllers\AuthController::class, 'getAllPermissions']);
    // Department management (super admin or users with admin/task config permission)
    Route::post('/create-department', [SuperAdminController::class, 'createDepartment']);
    Route::put('/update-department/{id}', [SuperAdminController::class, 'updateDepartment']);
    Route::delete('/delete-department/{id}', [SuperAdminController::class, 'deleteDepartment']);
});

// Super Admin Routes - allow authenticated users with proper permissions
Route::prefix('super-admin')->middleware(['auth:sanctum', 'user.active'])->group(function () {
    Route::post('/create-user', [SuperAdminController::class, 'createUser']); // Only super_admin
    Route::put('/update-user/{id}', [SuperAdminController::class, 'updateUser']); // Requires actors permission or own profile
});

// Routes that strictly require super_admin type
Route::prefix('super-admin')->middleware(['auth:sanctum', 'super.admin'])->group(function () {
    Route::post('/create-categorie', [SuperAdminController::class, 'createCategory']); // Note: keeping original spelling
    Route::put('/update-categorie/{id}', [SuperAdminController::class, 'updateCategory']);
    Route::delete('/delete-categorie/{id}', [SuperAdminController::class, 'deleteCategory']);
    Route::post('/create-task-name', [SuperAdminController::class, 'createTaskName']);
    Route::put('/update-task-name/{id}', [SuperAdminController::class, 'updateTaskName']);
    Route::delete('/delete-task-name/{id}', [SuperAdminController::class, 'deleteTaskName']);
    Route::post('/create-permission', [SuperAdminController::class, 'createPermission']);
    Route::put('/update-permission/{id}', [SuperAdminController::class, 'updatePermission']);
    Route::delete('/delete-permission/{id}', [SuperAdminController::class, 'deletePermission']);
    Route::post('/create-role', [SuperAdminController::class, 'createRole']);
    Route::put('/update-role/{id}', [SuperAdminController::class, 'updateRole']);
    Route::delete('/delete-role/{id}', [SuperAdminController::class, 'deleteRole']);
    Route::post('/assign-user-roles-permissions', [SuperAdminController::class, 'assignUserRolesPermissions']);
});

// File Upload Routes
Route::prefix('admin')->middleware(['auth:sanctum', 'user.active'])->group(function () {
    Route::post('/upload-file', [App\Http\Controllers\FileController::class, 'upload']);
    Route::post('/upload-file-url', [App\Http\Controllers\FileController::class, 'uploadFromUrl']);
    Route::get('/file/{id}', [App\Http\Controllers\FileController::class, 'getFile']);
});

// Admin Routes (Tasks)
Route::prefix('admin')->middleware(['auth:sanctum', 'user.active'])->group(function () {
    Route::post('/create-task', [App\Http\Controllers\Admin\TaskController::class, 'create']);
    Route::get('/task-counts', [App\Http\Controllers\Admin\TaskController::class, 'getTaskCountsByType']);
    Route::get('/task-counts-status', [App\Http\Controllers\Admin\TaskController::class, 'getTaskCountsByStatus']);
    Route::get('/tasks', [App\Http\Controllers\Admin\TaskController::class, 'index']);
    Route::get('/tasks/{id}', [App\Http\Controllers\Admin\TaskController::class, 'show']);
    Route::put('/tasks/{id}', [App\Http\Controllers\Admin\TaskController::class, 'update']);
    Route::delete('/tasks/{id}', [App\Http\Controllers\Admin\TaskController::class, 'destroy']);
    Route::post('/tasks/{id}/refuse', [App\Http\Controllers\Admin\TaskController::class, 'refuse']);
    Route::get('/tasks/{id}/refuse-history', [App\Http\Controllers\Admin\TaskController::class, 'getRefuseHistory']);
    Route::post('/tasks/{id}/request-delay', [App\Http\Controllers\Admin\TaskController::class, 'requestDelay']);
});

// Notification Routes
Route::prefix('notifications')->middleware(['auth:sanctum', 'user.active'])->group(function () {
    Route::get('/test-config', [App\Http\Controllers\NotificationController::class, 'testConfiguration']);
    Route::post('/send', [App\Http\Controllers\NotificationController::class, 'sendNotification']);
    Route::post('/send-bulk', [App\Http\Controllers\NotificationController::class, 'sendBulkNotification']);
});
