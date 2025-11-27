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
    private $messaging;

    public function __construct()
    {
        try {
            $firebaseConfig = config('services.firebase');
            
            // Prepare Firebase credentials
            $privateKey = $firebaseConfig['private_key'];
            // Handle newlines - replace literal \n with actual newlines
            $privateKey = str_replace(['\\n', '\n'], "\n", $privateKey);
            
            $credentials = [
                'type' => $firebaseConfig['type'],
                'project_id' => $firebaseConfig['project_id'],
                'private_key_id' => $firebaseConfig['private_key_id'],
                'private_key' => $privateKey,
                'client_email' => $firebaseConfig['client_email'],
                'client_id' => $firebaseConfig['client_id'],
                'auth_uri' => $firebaseConfig['auth_uri'],
                'token_uri' => $firebaseConfig['token_uri'],
                'auth_provider_x509_cert_url' => $firebaseConfig['auth_provider_x509_cert_url'],
                'client_x509_cert_url' => $firebaseConfig['client_x509_cert_url'],
                'universe_domain' => $firebaseConfig['universe_domain'],
            ];

            $factory = (new Factory)->withServiceAccount($credentials);
            $this->messaging = $factory->createMessaging();
        } catch (\Exception $e) {
            Log::error('Firebase initialization error: ' . $e->getMessage());
            $this->messaging = null;
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

            // Check if Firebase messaging is initialized
            if (!$this->messaging) {
                return response()->json([
                    'success' => false,
                    'message' => 'Firebase messaging is not configured properly.',
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
            $result = $this->messaging->send($message);

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

            if (!$this->messaging) {
                return response()->json([
                    'success' => false,
                    'message' => 'Firebase messaging is not configured properly.',
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

                    $this->messaging->send($message);
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

