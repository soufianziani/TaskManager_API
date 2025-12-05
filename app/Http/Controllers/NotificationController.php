<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\TaskNotification;
use App\Models\NotificationTimeout;
use App\Models\AlarmNotification;
use App\Models\Task;
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

    /**
     * List all notifications for the authenticated user (for Notification page).
     * Includes:
     * - Task notifications (assigned, status updated)
     * - Timeout notifications from notification_timeouts
     */
    public function listUserNotifications(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $userId = $user->id;

        // Whether to show read timeout notifications (read = 1) instead of unread (read = 0)
        $showReadTimeouts = $request->query('show_read', '0') === '1';

        // Task notifications (from TaskNotification table)
        $taskNotifications = TaskNotification::with('task')
            ->where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function (TaskNotification $n) {
                return [
                    'id' => $n->id,
                    'source' => 'task',
                    'type' => $n->type,
                    'title' => $n->title,
                    'body' => $n->body,
                    'task_id' => $n->task_id,
                    'task_name' => $n->task->name ?? null,
                    'created_at' => $n->created_at,
                ];
            });

        // Timeout notifications (from notification_timeouts)
        // If show_read=1, return read=1 (history); otherwise return unread (read=0)
        $timeoutQuery = NotificationTimeout::with('task')
            ->where('users_id', (string)$userId)
            ->where('read', $showReadTimeouts ? 1 : 0)
            ->orderBy('created_at', 'desc');

        $timeoutNotifications = $timeoutQuery
            ->get()
            ->map(function (NotificationTimeout $n) {
                return [
                    'id' => $n->id,
                    'source' => 'timeout',
                    'type' => 'timeout',
                    'title' => 'Task Timeout',
                    'body' => $n->description,
                    'task_id' => $n->task_id,
                    'task_name' => $n->task->name ?? null,
                    'created_at' => $n->created_at,
                    'next' => $n->next,
                    'read' => $n->read,
                ];
            });

        // Alarm notifications (from alarm_notifications)
        // If show_read=1, return read=1 (history); otherwise return unread (read=0)
        $alarmQuery = AlarmNotification::with('task')
            ->where('users_id', (string)$userId)
            ->where('read', $showReadTimeouts ? 1 : 0)
            ->orderBy('created_at', 'desc');

        $alarmNotifications = $alarmQuery
            ->get()
            ->map(function (AlarmNotification $n) {
                return [
                    'id' => $n->id,
                    'source' => 'alarm',
                    'type' => 'alarm',
                    'title' => 'Task Alarm',
                    'body' => $n->description,
                    'task_id' => $n->task_id,
                    'task_name' => $n->task->name ?? null,
                    'created_at' => $n->created_at,
                    'next' => $n->next,
                    'read' => $n->read,
                    'notification_count' => $n->notification_count,
                    'rest_max' => $n->rest_max,
                ];
            });

        // Merge and sort by created_at descending
        $all = $taskNotifications
            ->merge($timeoutNotifications)
            ->merge($alarmNotifications)
            ->sortByDesc('created_at')
            ->values();

        return response()->json([
            'success' => true,
            'message' => 'Notifications retrieved successfully',
            'data' => $all,
        ], 200);
    }

    /**
     * Mark a notification_timeout as read (set read = 1)
     */
    public function markTimeoutNotificationAsRead(Request $request, $id): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        try {
            $notificationTimeout = NotificationTimeout::where('id', $id)
                ->where('users_id', (string)$user->id)
                ->first();

            if (!$notificationTimeout) {
                return response()->json([
                    'success' => false,
                    'message' => 'Notification not found or you do not have permission to mark it as read.',
                ], 404);
            }

            $notificationTimeout->read = 1;
            $notificationTimeout->save();

            return response()->json([
                'success' => true,
                'message' => 'Notification marked as read successfully',
                'data' => [
                    'id' => $notificationTimeout->id,
                    'read' => $notificationTimeout->read,
                ],
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error marking notification as read', [
                'notification_id' => $id,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while marking notification as read: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Mark an alarm notification as read (set read = 1)
     */
    public function markAlarmNotificationAsRead(Request $request, $id): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        try {
            $alarmNotification = AlarmNotification::where('id', $id)
                ->where('users_id', (string)$user->id)
                ->first();

            if (!$alarmNotification) {
                return response()->json([
                    'success' => false,
                    'message' => 'Notification not found or you do not have permission to mark it as read.',
                ], 404);
            }

            $alarmNotification->read = 1;
            $alarmNotification->save();

            return response()->json([
                'success' => true,
                'message' => 'Notification marked as read successfully',
                'data' => [
                    'id' => $alarmNotification->id,
                    'read' => $alarmNotification->read,
                ],
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error marking alarm notification as read', [
                'notification_id' => $id,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while marking notification as read: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Mark a notification_timeout as deleted (set read = 2)
     */
    public function deleteTimeoutNotification(Request $request, $id): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        try {
            $notificationTimeout = NotificationTimeout::where('id', $id)
                ->where('users_id', (string)$user->id)
                ->first();

            if (!$notificationTimeout) {
                return response()->json([
                    'success' => false,
                    'message' => 'Notification not found or you do not have permission to delete it.',
                ], 404);
            }

            $notificationTimeout->read = 2;
            $notificationTimeout->save();

            return response()->json([
                'success' => true,
                'message' => 'Notification deleted successfully',
                'data' => [
                    'id' => $notificationTimeout->id,
                    'read' => $notificationTimeout->read,
                ],
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error deleting notification_timeout', [
                'notification_id' => $id,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while deleting notification: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Mark an alarm notification as deleted (set read = 2)
     */
    public function deleteAlarmNotification(Request $request, $id): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        try {
            $alarmNotification = AlarmNotification::where('id', $id)
                ->where('users_id', (string)$user->id)
                ->first();

            if (!$alarmNotification) {
                return response()->json([
                    'success' => false,
                    'message' => 'Notification not found or you do not have permission to delete it.',
                ], 404);
            }

            $alarmNotification->read = 2;
            $alarmNotification->save();

            return response()->json([
                'success' => true,
                'message' => 'Notification deleted successfully',
                'data' => [
                    'id' => $alarmNotification->id,
                    'read' => $alarmNotification->read,
                ],
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error deleting alarm notification', [
                'notification_id' => $id,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while deleting notification: ' . $e->getMessage(),
            ], 500);
        }
    }
}

