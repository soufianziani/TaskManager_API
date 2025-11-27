<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;

class NotificationController extends Controller
{
    private $messaging = null;
    private $initializationError = null;

    /**
     * Initialize Firebase messaging (lazy loading)
     */
    private function getMessaging()
    {
        if ($this->messaging !== null) {
            return $this->messaging;
        }

        if ($this->initializationError !== null) {
            return null;
        }

        try {
            $firebaseConfig = config('services.firebase');
            
            if (!$firebaseConfig || empty($firebaseConfig['project_id'])) {
                throw new \Exception('Firebase configuration is missing');
            }
            
            // Prepare Firebase credentials
            $privateKey = $firebaseConfig['private_key'] ?? '';
            
            // Handle newlines - replace literal \n with actual newlines
            // Handle both escaped and literal newline characters
            if (!empty($privateKey)) {
                // Replace escaped newlines (\n) with actual newlines
                $privateKey = str_replace(['\\n', '\n'], "\n", $privateKey);
                // Log private key info for debugging (first 50 chars only for security)
                Log::debug('Firebase private key loaded', [
                    'length' => strlen($privateKey),
                    'starts_with' => substr($privateKey, 0, 30),
                ]);
            }
            
            $credentials = [
                'type' => $firebaseConfig['type'] ?? 'service_account',
                'project_id' => $firebaseConfig['project_id'],
                'private_key_id' => $firebaseConfig['private_key_id'] ?? '',
                'private_key' => $privateKey,
                'client_email' => $firebaseConfig['client_email'] ?? '',
                'client_id' => $firebaseConfig['client_id'] ?? '',
                'auth_uri' => $firebaseConfig['auth_uri'] ?? 'https://accounts.google.com/o/oauth2/auth',
                'token_uri' => $firebaseConfig['token_uri'] ?? 'https://oauth2.googleapis.com/token',
                'auth_provider_x509_cert_url' => $firebaseConfig['auth_provider_x509_cert_url'] ?? 'https://www.googleapis.com/oauth2/v1/certs',
                'client_x509_cert_url' => $firebaseConfig['client_x509_cert_url'] ?? '',
                'universe_domain' => $firebaseConfig['universe_domain'] ?? 'googleapis.com',
            ];

            // Validate required fields
            if (empty($credentials['private_key']) || empty($credentials['client_email'])) {
                throw new \Exception('Firebase private_key or client_email is missing');
            }

            // Create temporary JSON file for Firebase credentials (preferred method)
            $tempFile = tempnam(sys_get_temp_dir(), 'firebase_credentials_');
            // Use JSON_UNESCAPED_SLASHES to preserve forward slashes and JSON_PRETTY_PRINT for readability
            file_put_contents($tempFile, json_encode($credentials, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
            
            try {
                $factory = (new Factory)->withServiceAccount($tempFile);
                $this->messaging = $factory->createMessaging();
                
                Log::info('Firebase messaging initialized successfully');
                
                return $this->messaging;
            } finally {
                // Clean up temporary file
                if (file_exists($tempFile)) {
                    @unlink($tempFile);
                }
            }
        } catch (\Exception $e) {
            $this->initializationError = $e->getMessage();
            Log::error('Firebase initialization error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'config_available' => !empty($firebaseConfig),
            ]);
            return null;
        }
    }

    /**
     * Test Firebase configuration
     */
    public function testConfiguration(Request $request): JsonResponse
    {
        try {
            $firebaseConfig = config('services.firebase');
            
            $messaging = $this->getMessaging();
            
            if (!$messaging) {
                return response()->json([
                    'success' => false,
                    'message' => 'Firebase messaging initialization failed',
                    'error' => $this->initializationError,
                    'config_check' => [
                        'config_exists' => !empty($firebaseConfig),
                        'project_id' => $firebaseConfig['project_id'] ?? 'missing',
                        'client_email' => !empty($firebaseConfig['client_email'] ?? ''),
                        'private_key' => !empty($firebaseConfig['private_key'] ?? ''),
                        'private_key_length' => strlen($firebaseConfig['private_key'] ?? ''),
                    ],
                ], 500);
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Firebase messaging is configured correctly',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error testing configuration: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Send push notification to a user by username
     */
    public function sendNotification(Request $request): JsonResponse
    {
        $request->validate([
            'user_name' => ['required', 'string'],
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
        ]);

        try {
            // Find user by username
            $user = User::where('user_name', $request->user_name)->first();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found.',
                ], 404);
            }

            // Check if user has FCM token
            if (!$user->fcm_token) {
                return response()->json([
                    'success' => false,
                    'message' => 'User does not have an FCM token. Please ensure the user has logged in from a device.',
                ], 400);
            }

            // Initialize Firebase messaging
            $messaging = $this->getMessaging();
            if (!$messaging) {
                return response()->json([
                    'success' => false,
                    'message' => 'Firebase messaging is not configured properly. ' . ($this->initializationError ?? 'Unknown error'),
                ], 500);
            }

            // Create notification
            $notification = Notification::create(
                $request->title,
                $request->body
            );

            // Create message
            $message = CloudMessage::withTarget('token', $user->fcm_token)
                ->withNotification($notification)
                ->withData([
                    'title' => $request->title,
                    'body' => $request->body,
                    'user_name' => $user->user_name,
                ]);

            // Send notification
            $result = $messaging->send($message);

            Log::info('Notification sent successfully', [
                'user_name' => $user->user_name,
                'user_id' => $user->id,
                'message_id' => $result,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Notification sent successfully',
                'data' => [
                    'user_name' => $user->user_name,
                    'user_id' => $user->id,
                    'message_id' => $result,
                ],
            ]);
        } catch (\Kreait\Firebase\Exception\MessagingException $e) {
            Log::error('Firebase messaging error: ' . $e->getMessage(), [
                'user_name' => $request->user_name,
                'error_code' => $e->getCode(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to send notification: ' . $e->getMessage(),
            ], 500);
        } catch (\Exception $e) {
            Log::error('Notification error: ' . $e->getMessage(), [
                'user_name' => $request->user_name,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while sending notification: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Send push notification to multiple users
     */
    public function sendBulkNotification(Request $request): JsonResponse
    {
        $request->validate([
            'user_names' => ['required', 'array', 'min:1'],
            'user_names.*' => ['required', 'string'],
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
        ]);

        try {
            $userNames = $request->user_names;
            $users = User::whereIn('user_name', $userNames)
                ->whereNotNull('fcm_token')
                ->get();

            if ($users->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No users found with FCM tokens.',
                ], 404);
            }

            // Initialize Firebase messaging
            $messaging = $this->getMessaging();
            if (!$messaging) {
                return response()->json([
                    'success' => false,
                    'message' => 'Firebase messaging is not configured properly. ' . ($this->initializationError ?? 'Unknown error'),
                ], 500);
            }

            $notification = Notification::create(
                $request->title,
                $request->body
            );

            $successCount = 0;
            $failedCount = 0;
            $errors = [];

            foreach ($users as $user) {
                try {
                    $message = CloudMessage::withTarget('token', $user->fcm_token)
                        ->withNotification($notification)
                        ->withData([
                            'title' => $request->title,
                            'body' => $request->body,
                            'user_name' => $user->user_name,
                        ]);

                    $messaging->send($message);
                    $successCount++;
                } catch (\Exception $e) {
                    $failedCount++;
                    $errors[] = [
                        'user_name' => $user->user_name,
                        'error' => $e->getMessage(),
                    ];
                    Log::error('Failed to send notification to user', [
                        'user_name' => $user->user_name,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            return response()->json([
                'success' => true,
                'message' => "Notifications sent. Success: {$successCount}, Failed: {$failedCount}",
                'data' => [
                    'success_count' => $successCount,
                    'failed_count' => $failedCount,
                    'errors' => $errors,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Bulk notification error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while sending notifications: ' . $e->getMessage(),
            ], 500);
        }
    }
}

