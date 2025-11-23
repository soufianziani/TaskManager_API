<?php

namespace App\Http\Controllers;

use App\Http\Requests\RequestOtpRequest;
use App\Http\Requests\VerifyOtpRequest;
use App\Models\User;
use App\Services\OtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class OtpController extends Controller
{
    public function __construct(
        private OtpService $otpService
    ) {
    }

    /**
     * Request OTP via WhatsApp
     */
    public function requestOtpWhatsApp(RequestOtpRequest $request): JsonResponse
    {
        $phoneNumber = $request->phone_number;
        $purpose = $request->purpose ?? 'login';

        // Normalize phone number for checking
        $normalizedPhone = $this->otpService->normalizePhoneNumber($phoneNumber);

        // Find user if exists
        $user = User::where('phone_number', $normalizedPhone)
            ->orWhere('whatsapp_number', $normalizedPhone)
            ->first();

        // IMPORTANT: Check if user exists and is active BEFORE sending OTP to avoid wasting money
        // Exception: Allow for 'register' purpose even if user doesn't exist
        if ($purpose !== 'register') {
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found. Please register first.',
                ], 404);
            }

            if ($user->is_active !== true) {
                return response()->json([
                    'success' => false,
                    'message' => 'User account is not active. Please contact support.',
                    'data' => [
                        'user_exists' => true,
                        'user_is_active' => false,
                    ],
                ], 403);
            }
        }

        // Only send OTP if user exists and is active (or for register purpose)
        $otpLog = $this->otpService->requestOtpViaWhatsApp($phoneNumber, $purpose, $user);

        if ($otpLog->status === 'sent') {
            $responseData = [
                'expires_at' => $otpLog->expires_at,
            ];

            // Add user information if exists (we already have $user from earlier check)
            if ($user) {
                $responseData['user'] = [
                    'id' => $user->id,
                    'user_name' => $user->user_name,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'phone_number' => $user->phone_number,
                    'is_active' => $user->is_active,
                    'is_number_validated' => $user->is_number_validated,
                ];
                $responseData['user_exists'] = true;
                $responseData['user_is_active'] = $user->is_active === true;
            } else {
                $responseData['user_exists'] = false;
                $responseData['user_is_active'] = false;
            }

            return response()->json([
                'success' => true,
                'message' => 'OTP sent successfully via WhatsApp',
                'data' => $responseData,
            ]);
        }

        // Get error details from OtpLog error_response
        $errorMessage = 'Failed to send OTP via WhatsApp';
        $errorDetails = [];
        
        if ($otpLog->error_response) {
            $errorResponse = $otpLog->error_response;
            
            // Extract error message from API response
            if (isset($errorResponse['response_json']['message'])) {
                $errorMessage = 'WhatsApp API Error: ' . $errorResponse['response_json']['message'];
            } elseif (isset($errorResponse['exception']) && isset($errorResponse['error_message'])) {
                $errorMessage = 'WhatsApp API Exception: ' . $errorResponse['error_message'];
            } elseif (isset($errorResponse['response_body'])) {
                $errorMessage = 'WhatsApp API Error: ' . $errorResponse['response_body'];
            }
            
            // Include full error details
            $errorDetails = [
                'status_code' => $errorResponse['status_code'] ?? null,
                'api_response' => $errorResponse['response_json'] ?? null,
                'raw_response' => $errorResponse['response_body'] ?? null,
                'api_url' => $errorResponse['url'] ?? null,
            ];
            
            if (isset($errorResponse['exception'])) {
                $errorDetails['exception'] = [
                    'message' => $errorResponse['error_message'] ?? null,
                    'file' => $errorResponse['error_file'] ?? null,
                    'line' => $errorResponse['error_line'] ?? null,
                ];
            }
        }

        return response()->json([
            'success' => false,
            'message' => $errorMessage,
            'data' => [
                'status' => $otpLog->status,
                'otp_log_id' => $otpLog->id,
                'verification_code' => $otpLog->verification_code, // For testing purposes
                'errors' => $errorDetails,
            ],
        ], 500);
    }

    /**
     * Request OTP via SMS
     */
    public function requestOtpSms(RequestOtpRequest $request): JsonResponse
    {
        $phoneNumber = $request->phone_number;
        $purpose = $request->purpose ?? 'login';

        // Normalize phone number for checking
        $normalizedPhone = $this->otpService->normalizePhoneNumber($phoneNumber);

        // Find user if exists (SMS uses phone_number only)
        $user = User::where('phone_number', $normalizedPhone)->first();

        // IMPORTANT: Check if user exists and is active BEFORE sending OTP to avoid wasting money
        // Exception: Allow for 'register' purpose even if user doesn't exist
        if ($purpose !== 'register') {
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found. Please register first.',
                ], 404);
            }

            if ($user->is_active !== true) {
                return response()->json([
                    'success' => false,
                    'message' => 'User account is not active. Please contact support.',
                    'data' => [
                        'user_exists' => true,
                        'user_is_active' => false,
                    ],
                ], 403);
            }
        }

        // Only send OTP if user exists and is active (or for register purpose)
        $otpLog = $this->otpService->requestOtpViaSms($phoneNumber, $purpose, $user);

        if ($otpLog->status === 'sent') {
            $responseData = [
                'expires_at' => $otpLog->expires_at,
            ];

            // Add user information if exists (we already have $user from earlier check)
            if ($user) {
                $responseData['user'] = [
                    'id' => $user->id,
                    'user_name' => $user->user_name,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'phone_number' => $user->phone_number,
                    'is_active' => $user->is_active,
                    'is_number_validated' => $user->is_number_validated,
                ];
                $responseData['user_exists'] = true;
                $responseData['user_is_active'] = $user->is_active === true;
            } else {
                $responseData['user_exists'] = false;
                $responseData['user_is_active'] = false;
            }

            return response()->json([
                'success' => true,
                'message' => 'OTP sent successfully via SMS',
                'data' => $responseData,
            ]);
        }

        // Get error details from OtpLog error_response
        $errorMessage = 'Failed to send OTP via SMS';
        $errorDetails = [];
        
        if ($otpLog->error_response) {
            $errorResponse = $otpLog->error_response;
            
            // Extract error message from API response
            if (isset($errorResponse['response_json']['message'])) {
                $errorMessage = 'SMS API Error: ' . $errorResponse['response_json']['message'];
            } elseif (isset($errorResponse['exception']) && isset($errorResponse['error_message'])) {
                $errorMessage = 'SMS API Exception: ' . $errorResponse['error_message'];
            } elseif (isset($errorResponse['response_body'])) {
                $errorMessage = 'SMS API Error: ' . $errorResponse['response_body'];
            }
            
            // Include full error details
            $errorDetails = [
                'status_code' => $errorResponse['status_code'] ?? null,
                'api_response' => $errorResponse['response_json'] ?? null,
                'raw_response' => $errorResponse['response_body'] ?? null,
                'api_url' => $errorResponse['url'] ?? null,
            ];
            
            if (isset($errorResponse['exception'])) {
                $errorDetails['exception'] = [
                    'message' => $errorResponse['error_message'] ?? null,
                    'file' => $errorResponse['error_file'] ?? null,
                    'line' => $errorResponse['error_line'] ?? null,
                ];
            }
        }

        return response()->json([
            'success' => false,
            'message' => $errorMessage,
            'data' => [
                'status' => $otpLog->status,
                'otp_log_id' => $otpLog->id,
                'verification_code' => $otpLog->verification_code, // For testing purposes
                'errors' => $errorDetails,
            ],
        ], 500);
    }

    /**
     * Verify OTP code
     * For register purpose: validates phone number (is_number_validated = 1)
     * User is already active by default during registration
     */
    public function verifyOtp(VerifyOtpRequest $request): JsonResponse
    {
        $result = $this->otpService->verifyOtp(
            $request->phone_number,
            $request->verification_code,
            $request->purpose
        );

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'],
            ], 400);
        }

        // If OTP verified for register purpose, validate phone number
        if ($request->purpose === 'register' && $result['user']) {
            $result['user']->update([
                'is_active' => true, // Ensure active (already true by default, but ensures consistency)
                'is_number_validated' => true,
                'validated_by' => $result['otp_log']->provider,
            ]);
            $result['user']->refresh();
        }

        // Create authentication token if user exists
        $token = null;
        if ($result['user']) {
            $token = $result['user']->createToken('auth-token')->plainTextToken;
        }

        $responseData = [
            'verified_at' => $result['otp_log']->verified_at,
        ];

        // Add user and token to response if user exists
        if ($result['user']) {
            $responseData['user'] = $result['user']->load(['roles', 'permissions']);
            $responseData['token'] = $token;
        }

        return response()->json([
            'success' => true,
            'message' => $result['message'],
            'data' => $responseData,
        ]);
    }

    /**
     * Handle WhatsApp webhook callback
     * This endpoint receives callbacks from the WhatsApp API about message delivery status
     */
    public function whatsappWebhook(Request $request): JsonResponse
    {
        // Log the webhook payload for debugging
        Log::info('WhatsApp webhook callback received', [
            'payload' => $request->all(),
            'headers' => $request->headers->all(),
        ]);

        // Process webhook data (status updates, delivery confirmations, etc.)
        // You can update the OTP log status based on webhook response
        // For example: if message was delivered, mark as 'sent', if failed, mark as 'failed'

        // Return success response to acknowledge receipt
        return response()->json([
            'success' => true,
            'message' => 'Webhook received',
        ]);
    }
}
