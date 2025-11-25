<?php

namespace App\Http\Controllers;

use App\Http\Requests\RegisterRequest;
use App\Http\Requests\SetPasswordRequest;
use App\Http\Requests\VerifyOtpRequest;
use App\Models\Category;
use App\Models\Department;
use App\Models\Type;
use App\Models\User;
use App\Services\OtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class AuthController extends Controller
{
    public function __construct(
        private OtpService $otpService
    ) {
    }

    /**
     * Login user with OTP and return authentication token.
     */
    public function loginWithOtp(VerifyOtpRequest $request): JsonResponse
    {
        $result = $this->otpService->verifyOtp(
            $request->phone_number,
            $request->verification_code,
            'login'
        );

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'],
            ], 400);
        }

        $user = $result['user'];

        if (!$user) {
            // User doesn't exist, cannot login
            return response()->json([
                'success' => false,
                'message' => 'User not found. Please register first.',
            ], 404);
        }

        // Check if user is active
        if (!$user->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'User account is not active. Please complete your registration by setting a password.',
            ], 403);
        }

        // Update device info if provided
        if ($request->has('device_id')) {
            $user->update(['device_id' => $request->device_id]);
        }
        if ($request->has('fcm_token')) {
            $user->update(['fcm_token' => $request->fcm_token]);
        }

        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'data' => [
                'user' => $user->load(['roles', 'permissions']),
                'token' => $token,
            ],
        ]);
    }

    /**
     * Register user (simple registration without OTP verification).
     * User needs to verify OTP after registration to activate account.
     */
    public function registerWithOtp(RegisterRequest $request): JsonResponse
    {
        // Check if user already exists
        $existingUser = User::where('phone_number', $request->phone_number)->first();
        if ($existingUser) {
            return response()->json([
                'success' => false,
                'message' => 'User already exists. Please login instead.',
            ], 409);
        }

        // Generate unique numeric username
        $username = $this->otpService->generateUniqueUsername();

        // Generate unique FCM token if not provided or if it already exists
        $fcmToken = $request->fcm_token;
        if ($fcmToken && User::where('fcm_token', $fcmToken)->exists()) {
            // Generate a unique FCM token if the provided one is already in use
            $fcmToken = $this->otpService->generateUniqueFcmToken();
        } elseif (!$fcmToken) {
            // Generate a unique FCM token if not provided
            $fcmToken = $this->otpService->generateUniqueFcmToken();
        }

        // Create user (inactive by default, will be activated after password creation)
        $user = User::create([
            'user_name' => $username,
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'phone_number' => $request->phone_number,
            'phone' => $request->phone_number,
            'whatsapp_number' => $request->whatsapp_number,
            'support_number' => $request->support_number,
            'device_id' => $request->device_id,
            'fcm_token' => $fcmToken,
            'email' => $request->email,
            'is_number_validated' => false, // Will be set to true after OTP verification
            'is_active' => false, // Inactive by default, will be activated after password creation
        ]);

        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Registration successful. Please verify your phone number with OTP.',
            'data' => [
                'user' => $user,
                'token' => $token,
            ],
        ], 201);
    }

    /**
     * Login user with email/password (fallback method).
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        // Update device info if provided
        if ($request->has('device_id')) {
            $user->update(['device_id' => $request->device_id]);
        }
        if ($request->has('fcm_token')) {
            $user->update(['fcm_token' => $request->fcm_token]);
        }

        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'data' => [
                'user' => $user->load(['roles', 'permissions']),
                'token' => $token,
            ],
        ]);
    }

    /**
     * Logout user (revoke current token).
     */
    public function logout(Request $request): JsonResponse
    {
        /** @var \Laravel\Sanctum\PersonalAccessToken $token */
        $token = $request->user()->currentAccessToken();
        if ($token) {
            $token->delete();
        }

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully',
        ]);
    }

    /**
     * Get authenticated user.
     */
    public function user(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->load(['roles', 'permissions']);

        return response()->json([
            'success' => true,
            'data' => $user,
        ]);
    }

    /**
     * Get all users.
     */
    public function getAllUsers(Request $request): JsonResponse
    {
        $users = User::with(['roles', 'permissions'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Users retrieved successfully',
            'data' => $users,
        ]);
    }

    /**
     * Get all departments.
     */
    public function getAllDepartments(Request $request): JsonResponse
    {
        $departments = Department::where('is_active', true)
            ->orderBy('name', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Departments retrieved successfully',
            'data' => $departments,
        ]);
    }

    /**
     * Get all roles.
     */
    public function getAllRoles(Request $request): JsonResponse
    {
        $roles = Role::with('permissions')
            ->orderBy('name', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Roles retrieved successfully',
            'data' => $roles,
        ]);
    }

    /**
     * Get all permissions.
     */
    public function getAllPermissions(Request $request): JsonResponse
    {
        $permissions = Permission::orderBy('name', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Permissions retrieved successfully',
            'data' => $permissions,
        ]);
    }

    /**
     * Get all categories.
     */
    public function getAllCategories(Request $request): JsonResponse
    {
        $categories = Category::with('department')
            ->orderBy('name', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Categories retrieved successfully',
            'data' => $categories,
        ]);
    }

    /**
     * Get all types.
     */
    public function getAllTypes(Request $request): JsonResponse
    {
        $types = Type::with('department')
            ->orderBy('name', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Types retrieved successfully',
            'data' => $types,
        ]);
    }

    /**
     * Set password and activate user after OTP verification.
     * This endpoint is called after OTP verification during registration.
     */
    public function setPassword(SetPasswordRequest $request): JsonResponse
    {
        try {
            // Find user by phone number
            $user = User::where('phone_number', $request->phone_number)->first();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found.',
                ], 404);
            }

            // Check if user has verified their phone number
            if (!$user->is_number_validated) {
                return response()->json([
                    'success' => false,
                    'message' => 'Please verify your phone number with OTP first.',
                ], 400);
            }

            // Check if user already has a password and is active (already completed registration)
            // Allow setting password if:
            // 1. User doesn't have a password (new registration)
            // 2. User has password but is inactive (reactivation/reset scenario)
            // Prevent if user has password AND is active (already completed registration)
            if ($user->password && $user->is_active) {
                return response()->json([
                    'success' => false,
                    'message' => 'Password already set. User is already active. Please use password reset if you need to change your password.',
                ], 400);
            }

            // Set password and activate user
            // Note: User model has 'password' cast to 'hashed' (Laravel 10+), so direct assignment will auto-hash
            // Using direct assignment to avoid double-hashing
            $user->password = $request->password;
            $user->is_active = true;
            $user->save();

            $user->refresh();

            // Create authentication token
            $token = $user->createToken('auth-token')->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'Password set successfully. Account activated.',
                'data' => [
                    'user' => $user->load(['roles', 'permissions']),
                    'token' => $token,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while setting the password: ' . $e->getMessage(),
            ], 500);
        }
    }
}
