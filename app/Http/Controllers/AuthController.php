<?php

namespace App\Http\Controllers;

use App\Http\Requests\RegisterRequest;
use App\Http\Requests\VerifyOtpRequest;
use App\Models\User;
use App\Services\OtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

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

        // Generate unique username
        $username = $this->otpService->generateUniqueUsername();

        // Create user (active by default, phone number validation after OTP verification)
        $user = User::create([
            'user_name' => $username,
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'phone_number' => $request->phone_number,
            'phone' => $request->phone_number,
            'whatsapp_number' => $request->whatsapp_number,
            'support_number' => $request->support_number,
            'device_id' => $request->device_id,
            'fcm_token' => $request->fcm_token,
            'email' => $request->email,
            'is_number_validated' => false, // Will be set to true after OTP verification
            'is_active' => true, // Active by default
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
}
