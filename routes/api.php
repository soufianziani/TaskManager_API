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
});

// Super Admin Routes
Route::prefix('super-admin')->middleware(['auth:sanctum', 'super.admin'])->group(function () {
    Route::post('/create-department', [SuperAdminController::class, 'createDepartment']);
    Route::post('/create-categorie', [SuperAdminController::class, 'createCategory']); // Note: keeping original spelling
    Route::post('/create-type', [SuperAdminController::class, 'createType']);
    Route::post('/create-permission', [SuperAdminController::class, 'createPermission']);
    Route::post('/create-role', [SuperAdminController::class, 'createRole']);
    Route::post('/assign-user-roles-permissions', [SuperAdminController::class, 'assignUserRolesPermissions']);
});

// Admin Routes (Tasks)
Route::prefix('admin')->middleware(['auth:sanctum'])->group(function () {
    Route::post('/create-task', [App\Http\Controllers\Admin\TaskController::class, 'create']);
});
