<?php

namespace App\Http\Controllers;

use App\Http\Requests\RegisterRequest;
use App\Http\Requests\SetPasswordRequest;
use App\Http\Requests\VerifyOtpRequest;
use App\Models\Category;
use App\Models\Department;
use App\Models\TaskName;
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

        // Check if user has a password set
        if (!$user->password) {
            return response()->json([
                'success' => false,
                'message' => 'Please complete your registration by setting a password.',
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

        // Load roles and get all permissions (direct + via roles)
        $user->load(['roles']);
        // Get all permissions including those from roles
        $allPermissions = $user->getAllPermissions();
        
        // Add all permissions to user object for JSON response
        $user->setRelation('permissions', $allPermissions);

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'data' => [
                'user' => $user,
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

        // Do NOT return token here - user must complete OTP verification and password setup first
        // Token will be returned after password is set in setPassword method

        return response()->json([
            'success' => true,
            'message' => 'Registration successful. Please verify your phone number with OTP.',
            'data' => [
                'user' => $user,
                // No token returned - user must complete registration steps first
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

        if (!$user || !$user->password || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
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

        // Load roles and get all permissions (direct + via roles)
        $user->load(['roles']);
        // Get all permissions including those from roles
        $allPermissions = $user->getAllPermissions();
        
        // Add all permissions to user object for JSON response
        $user->setRelation('permissions', $allPermissions);

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'data' => [
                'user' => $user,
                'token' => $token,
            ],
        ]);
    }

    /**
     * Login user with username/password.
     */
    public function loginWithUsername(Request $request): JsonResponse
    {
        $request->validate([
            'user_name' => 'required|string',
            'password' => 'required',
            'fcm_token' => 'nullable|string',
            'device_id' => 'nullable|string',
        ]);

        $user = User::where('user_name', $request->user_name)->first();

        if (!$user || !$user->password || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid username or password.',
            ], 401);
        }

        // Check if user is active
        if (!$user->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'User account is not active. Please complete your registration by setting a password.',
            ], 403);
        }

        // Prepare update data
        $updateData = [
            'fcm_token' => $request->fcm_token,
        ];

        // Update device_id if provided (optional)
        if ($request->has('device_id') && $request->device_id) {
            $updateData['device_id'] = $request->device_id;
        }

        // Save fcm_token and optional device_id
        $user->update($updateData);

        $token = $user->createToken('auth-token')->plainTextToken;

        // Load roles and get all permissions (direct + via roles)
        $user->load(['roles']);
        // Get all permissions including those from roles
        $allPermissions = $user->getAllPermissions();
        
        // Add all permissions to user object for JSON response
        $user->setRelation('permissions', $allPermissions);

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'data' => [
                'user' => $user,
                'token' => $token,
            ],
        ]);
    }

    /**
     * Check token validity and return user info.
     */
    public function checkToken(Request $request): JsonResponse
    {
        $request->validate([
            'token' => 'required|string',
        ]);

        // Find the token in the database
        $accessToken = \Laravel\Sanctum\PersonalAccessToken::findToken($request->token);

        if (!$accessToken) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired token.',
            ], 401);
        }

        // Get the user associated with this token
        $user = $accessToken->tokenable;

        if (!$user || !$user instanceof User) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid token.',
            ], 401);
        }

        // Check if user is active
        if (!$user->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'User account is not active.',
            ], 403);
        }

        // Load roles and get all permissions (direct + via roles)
        $user->load(['roles']);
        // Get all permissions including those from roles
        $allPermissions = $user->getAllPermissions();
        
        // Add all permissions to user object for JSON response
        $user->setRelation('permissions', $allPermissions);

        return response()->json([
            'success' => true,
            'message' => 'Token is valid',
            'data' => [
                'user' => $user,
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
        // Load roles and get all permissions (direct + via roles)
        $user->load(['roles']);
        // Get all permissions including those from roles
        $allPermissions = $user->getAllPermissions();
        
        // Add all permissions to user object for JSON response
        $user->setRelation('permissions', $allPermissions);

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
        $includeInactive = $request->boolean('include_inactive', false);

        $departmentsQuery = Department::query();
        if (!$includeInactive) {
            $departmentsQuery->where('is_active', true);
        }

        $departments = $departmentsQuery
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
        $includeInactive = $request->boolean('include_inactive', false);

        $categoriesQuery = Category::with('department');
        if (!$includeInactive) {
            $categoriesQuery->where('is_active', true);
        }

        $categories = $categoriesQuery
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
    public function getAllTaskNames(Request $request): JsonResponse
    {
        $includeInactive = $request->boolean('include_inactive', false);

        $taskNamesQuery = TaskName::with('category');
        if (!$includeInactive) {
            $taskNamesQuery->where('is_active', true);
        }

        $taskNames = $taskNamesQuery
            ->orderBy('name', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Task names retrieved successfully',
            'data' => $taskNames,
        ]);
    }

    /**
     * Set password and activate user after OTP verification.
     * This endpoint is called after OTP verification during registration.
     * Can also be used with bearer token for password reset flow.
     */
    public function setPassword(SetPasswordRequest $request): JsonResponse
    {
        try {
            $user = null;

            // Check if bearer token is provided (for password reset flow)
            $bearerToken = $request->bearerToken();
            
            if ($bearerToken) {
                // Manually authenticate using the bearer token
                $accessToken = \Laravel\Sanctum\PersonalAccessToken::findToken($bearerToken);
                
                if (!$accessToken) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Invalid or expired token.',
                    ], 401);
                }

                $user = $accessToken->tokenable;

                if (!$user || !$user instanceof User) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Invalid token.',
                    ], 401);
                }

                // Check if user is active
                if (!$user->is_active) {
                    return response()->json([
                        'success' => false,
                        'message' => 'User account is not active.',
                    ], 403);
                }
            } elseif ($request->user()) {
                // User authenticated via middleware (if route has auth middleware)
                $user = $request->user();
            } else {
                // Find user by phone number (for registration flow)
                if (!$request->phone_number) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Phone number is required when no bearer token is provided.',
                    ], 400);
                }

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
            }

            // Update FCM token if provided
            if ($request->has('fcm_token')) {
                $user->update(['fcm_token' => $request->fcm_token]);
            }

            // Set password and activate user (if not already active)
            // Note: User model has 'password' cast to 'hashed' (Laravel 10+), so direct assignment will auto-hash
            // Using direct assignment to avoid double-hashing
            $user->password = $request->password;
            if (!$user->is_active) {
                $user->is_active = true;
            }
            $user->save();

            $user->refresh();

            // Create authentication token
            // For registration flow: create new token
            // For password reset flow: create new token (user is already authenticated via bearer token)
            $token = $user->createToken('auth-token')->plainTextToken;

            // Load roles and get all permissions (direct + via roles)
            $user->load(['roles']);
            // Get all permissions including those from roles
            $allPermissions = $user->getAllPermissions();
            
            // Add all permissions to user object for JSON response
            $user->setRelation('permissions', $allPermissions);

            return response()->json([
                'success' => true,
                'message' => 'Password set successfully. Account activated.',
                'data' => [
                    'user' => $user,
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

    /**
     * Update user password (requires authentication).
     * User must provide current password to update to a new password.
     */
    public function updatePassword(Request $request): JsonResponse
    {
        $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ]);

        $user = $request->user();

        // Verify current password
        if (!$user->password || !Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Current password is incorrect.',
            ], 400);
        }

        // Check if new password is same as current password
        if (Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'New password must be different from current password.',
            ], 400);
        }

        try {
            // Update password (Laravel will auto-hash it)
            $user->password = $request->password;
            $user->save();

            return response()->json([
                'success' => true,
                'message' => 'Password updated successfully.',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while updating the password: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Request OTP for password reset (forgot password).
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate([
            'phone_number' => ['required', 'string'],
        ]);

        // Normalize phone number
        $normalizedPhone = $this->otpService->normalizePhoneNumber($request->phone_number);

        // Find user by phone number
        $user = User::where('phone_number', $normalizedPhone)->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found with this phone number.',
            ], 404);
        }

        // Check if user is active
        if (!$user->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'User account is not active. Please contact support.',
            ], 403);
        }

        // Request OTP via WhatsApp (you can also add SMS option)
        $otpLog = $this->otpService->requestOtpViaWhatsApp(
            $request->phone_number,
            'password_reset',
            $user
        );

        if ($otpLog->status === 'sent') {
            return response()->json([
                'success' => true,
                'message' => 'OTP sent successfully via WhatsApp for password reset.',
                'data' => [
                    'user_name' => $user->user_name,
                    'expires_at' => $otpLog->expires_at,
                ],
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Failed to send OTP. Please try again.',
        ], 500);
    }

    /**
     * Verify OTP for password reset and return bearer token.
     * After this, user should call set-password with the bearer token.
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'phone_number' => ['required', 'string'],
            'verification_code' => ['required', 'string'],
        ]);

        // Verify OTP
        $result = $this->otpService->verifyOtp(
            $request->phone_number,
            $request->verification_code,
            'password_reset'
        );

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'],
            ], 400);
        }

        $user = $result['user'];

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found.',
            ], 404);
        }

        // Check if user is active
        if (!$user->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'User account is not active.',
            ], 403);
        }

        // Update FCM token if provided
        if ($request->has('fcm_token')) {
            $user->update(['fcm_token' => $request->fcm_token]);
        }

        // Create authentication token
        $token = $user->createToken('auth-token')->plainTextToken;

        // Load roles and get all permissions (direct + via roles)
        $user->load(['roles']);
        // Get all permissions including those from roles
        $allPermissions = $user->getAllPermissions();
        
        // Add all permissions to user object for JSON response
        $user->setRelation('permissions', $allPermissions);

        return response()->json([
            'success' => true,
            'message' => 'OTP verified successfully. Please set your new password.',
            'data' => [
                'user' => $user,
                'token' => $token,
            ],
        ]);
    }
}
