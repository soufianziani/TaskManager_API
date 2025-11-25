<?php

namespace App\Services;

use App\Models\OtpLog;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class OtpService
{
    /**
     * Get WhatsApp API base URL
     * Uses configurable base URL from .env file
     */
    private function getWhatsAppApiBaseUrl(): string
    {
        return config('services.whatsapp.base_url', 'https://connect.wadina.agency/webhooks');
    }

    /**
     * Get WhatsApp webhook callback URL
     */
    private function getWhatsAppWebhookUrl(): string
    {
        $appUrl = config('app.url');
        return rtrim($appUrl, '/') . '/api/webhooks/whatsapp/callback';
    }

    /**
     * Get SMS API endpoint URL
     * Uses configurable base URL from .env file
     */
    private function getSmsApiUrl(): string
    {
        return config('services.infobip.base_url', 'https://xl4ln4.api.infobip.com/sms/2/text/advanced');
    }

    /**
     * OTP expiration time in minutes
     */
    private const OTP_EXPIRY_MINUTES = 10;

    /**
     * Generate a 4-digit OTP code
     */
    public function generateOtp(): string
    {
        return str_pad((string) rand(1000, 9999), 4, '0', STR_PAD_LEFT);
    }

    /**
     * Normalize phone number to international format
     * Handles +212, 0, 212 formats
     */
    public function normalizePhoneNumber(string $phoneNumber): string
    {
        // Remove spaces, dashes, and other characters
        $phone = preg_replace('/[^0-9+]/', '', $phoneNumber);

        // Handle Moroccan phone numbers
        if (Str::startsWith($phone, '0')) {
            // Convert 0XXXXXXXX to +212XXXXXXXX
            $phone = '+212' . substr($phone, 1);
        } elseif (Str::startsWith($phone, '212')) {
            // Add + if missing
            $phone = '+' . $phone;
        } elseif (!Str::startsWith($phone, '+')) {
            // If no prefix, assume it's already in international format
            $phone = '+' . $phone;
        }

        return $phone;
    }

    /**
     * Request OTP via WhatsApp
     */
    public function requestOtpViaWhatsApp(string $phoneNumber, ?string $purpose = 'login', ?User $user = null): OtpLog
    {
        $normalizedPhone = $this->normalizePhoneNumber($phoneNumber);
        $otpCode = $this->generateOtp();

        // Create OTP log
        $otpLog = OtpLog::create([
            'verification_code' => $otpCode,
            'phone_number' => $normalizedPhone,
            'status' => 'pending',
            'provider' => 'whatsapp',
            'user_id' => $user?->id,
            'purpose' => $purpose,
            'expires_at' => now()->addMinutes(self::OTP_EXPIRY_MINUTES),
        ]);

        // Test mode - don't actually send, just log the OTP
        if (config('services.whatsapp.test_mode', false)) {
            $otpLog->update(['status' => 'sent']);
            Log::info('OTP (TEST MODE) - WhatsApp', [
                'phone' => $normalizedPhone,
                'otp_code' => $otpCode,
                'otp_id' => $otpLog->id,
                'message' => "TEST MODE: OTP code {$otpCode} would be sent to {$normalizedPhone}",
            ]);
            return $otpLog;
        }

        try {
            // Get API key and base URL
            $apiKey = config('services.whatsapp.api_key');
            $baseUrl = $this->getWhatsAppApiBaseUrl();
            $webhookUrl = $this->getWhatsAppWebhookUrl();

            // Prepare request body according to API requirements
            $requestBody = [
                'type' => 'otp',
                'number' => $normalizedPhone,
                'otp' => $otpCode,
                'webhook' => $webhookUrl,
            ];

            // Build HTTP request with correct headers and format
            $httpClient = Http::withHeaders([
                'Content-Type' => 'application/json',
            ]);

            // Add API key in x-api-key header
            if ($apiKey) {
                $httpClient = $httpClient->withHeaders([
                    'x-api-key' => $apiKey,
                ]);
            }

            // Set timeout and base URL, then post to whatsapp endpoint
            $response = $httpClient
                ->timeout(30)
                ->baseUrl($baseUrl)
                ->post('whatsapp', $requestBody);

            if ($response->successful()) {
                $otpLog->update(['status' => 'sent']);
                Log::info('OTP sent via WhatsApp', [
                    'phone' => $normalizedPhone,
                    'otp_id' => $otpLog->id,
                    'response_status' => $response->status(),
                    'response_body' => $response->body(),
                ]);
            } else {
                $errorResponse = [
                    'status_code' => $response->status(),
                    'response_body' => $response->body(),
                    'response_json' => $response->json(),
                    'base_url' => $baseUrl,
                    'endpoint' => 'whatsapp',
                    'request_body' => $requestBody,
                ];
                
                $otpLog->update([
                    'status' => 'failed',
                    'error_response' => $errorResponse,
                ]);
                
                Log::error('Failed to send OTP via WhatsApp', [
                    'phone' => $normalizedPhone,
                    'otp_id' => $otpLog->id,
                    'error_response' => $errorResponse,
                ]);
            }
        } catch (\Exception $e) {
            $errorResponse = [
                'exception' => true,
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
            ];
            
            $otpLog->update([
                'status' => 'failed',
                'error_response' => $errorResponse,
            ]);
            
            Log::error('Exception sending OTP via WhatsApp', [
                'phone' => $normalizedPhone,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'otp_id' => $otpLog->id,
                'error_response' => $errorResponse,
            ]);
        }

        return $otpLog;
    }

    /**
     * Request OTP via SMS
     */
    public function requestOtpViaSms(string $phoneNumber, ?string $purpose = 'login', ?User $user = null): OtpLog
    {
        $normalizedPhone = $this->normalizePhoneNumber($phoneNumber);
        $otpCode = $this->generateOtp();

        // Create OTP log
        $otpLog = OtpLog::create([
            'verification_code' => $otpCode,
            'phone_number' => $normalizedPhone,
            'status' => 'pending',
            'provider' => 'sms',
            'user_id' => $user?->id,
            'purpose' => $purpose,
            'expires_at' => now()->addMinutes(self::OTP_EXPIRY_MINUTES),
        ]);

        try {
            // Send SMS via Infobip
            $message = "Your verification code is: {$otpCode}. Valid for " . self::OTP_EXPIRY_MINUTES . " minutes.";

            $smsUrl = $this->getSmsApiUrl();
            
            $response = Http::withHeaders([
                'Authorization' => 'App ' . config('services.infobip.api_key'),
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])->post($smsUrl, [
                'messages' => [
                    [
                        'from' => config('services.infobip.sender_id', 'TaskManager'),
                        'destinations' => [
                            ['to' => $normalizedPhone],
                        ],
                        'text' => $message,
                    ],
                ],
            ]);

            if ($response->successful()) {
                $otpLog->update(['status' => 'sent']);
                Log::info('OTP sent via SMS', ['phone' => $normalizedPhone, 'otp_id' => $otpLog->id]);
            } else {
                $errorResponse = [
                    'status_code' => $response->status(),
                    'response_body' => $response->body(),
                    'response_json' => $response->json(),
                    'url' => $smsUrl,
                ];
                
                $otpLog->update([
                    'status' => 'failed',
                    'error_response' => $errorResponse,
                ]);
                
                Log::error('Failed to send OTP via SMS', [
                    'phone' => $normalizedPhone,
                    'otp_id' => $otpLog->id,
                    'error_response' => $errorResponse,
                ]);
            }
        } catch (\Exception $e) {
            $errorResponse = [
                'exception' => true,
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
            ];
            
            $otpLog->update([
                'status' => 'failed',
                'error_response' => $errorResponse,
            ]);
            
            Log::error('Exception sending OTP via SMS', [
                'phone' => $normalizedPhone,
                'error' => $e->getMessage(),
                'otp_id' => $otpLog->id,
                'error_response' => $errorResponse,
            ]);
        }

        return $otpLog;
    }

    /**
     * Verify OTP code
     */
    public function verifyOtp(string $phoneNumber, string $code, ?string $purpose = null): array
    {
        $normalizedPhone = $this->normalizePhoneNumber($phoneNumber);

        // Find the most recent non-expired OTP for this phone
        $otpLog = OtpLog::where('phone_number', $normalizedPhone)
            ->where('verification_code', $code)
            ->where('status', '!=', 'confirmed')
            ->where('status', '!=', 'expired')
            ->when($purpose, fn($query) => $query->where('purpose', $purpose))
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$otpLog) {
            return [
                'success' => false,
                'message' => 'Invalid OTP code',
            ];
        }

        // Check if expired
        if ($otpLog->isExpired()) {
            $otpLog->markAsExpired();
            return [
                'success' => false,
                'message' => 'OTP code has expired',
            ];
        }

        // Mark as confirmed
        $otpLog->markAsConfirmed();

        // If user is not linked, try to find by phone number
        $user = $otpLog->user;
        if (!$user && $purpose === 'register') {
            $user = User::where('phone_number', $normalizedPhone)->first();
            // Update OTP log with user_id if found
            if ($user) {
                $otpLog->update(['user_id' => $user->id]);
            }
        }

        return [
            'success' => true,
            'message' => 'OTP verified successfully',
            'otp_log' => $otpLog,
            'user' => $user,
        ];
    }

    /**
     * Generate unique 4-digit numeric username (e.g., 1234, 4358)
     */
    public function generateUniqueUsername(): string
    {
        do {
            $username = str_pad((string) rand(1000, 9999), 4, '0', STR_PAD_LEFT);
        } while (User::where('user_name', $username)->exists());

        return $username;
    }

    /**
     * Generate unique FCM token
     */
    public function generateUniqueFcmToken(): string
    {
        do {
            $token = 'fcm_' . Str::random(32) . '_' . time();
        } while (User::where('fcm_token', $token)->exists());

        return $token;
    }
}

