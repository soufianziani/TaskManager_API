<?php

namespace App\Console\Commands;

use App\Models\Task;
use App\Models\User;
use App\Models\Delay;
use App\Models\NotificationTimeout;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
use Carbon\Carbon;

class CheckTaskTimeouts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tasks:check-timeouts';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check for tasks that have reached their timeout and send notifications';

    private $messaging = null;

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Checking for task timeouts...');

        $now = Carbon::now();
        $notifiedCount = 0;
        $skippedCount = 0;
        $delayExpiredCount = 0;

        // Note: With new delay structure (rest_time, rest_max), delays no longer have delay_until
        // The delay logic will be handled differently - rest_max tracks remaining delays

        // Get all tasks with time_cloture and time_out set
        // Include tasks where timeout_notified_at is null OR where there are no active delays
        $tasks = Task::whereNotNull('time_cloture')
            ->whereNotNull('time_out')
            ->where('status', true) // Only active tasks
            ->get()
            ->filter(function ($task) use ($now) {
                // Skip if already notified and no active delays exist
                if ($task->timeout_notified_at) {
                    // Check if there are any active delays for this task (rest_max > 0)
                    $activeDelays = Delay::where('task_id', (string)$task->id)
                        ->where('rest_max', '>', 0)
                        ->exists();
                    
                    // If there are active delays, don't process this task yet
                    return !$activeDelays;
                }
                return true;
            });

        if ($tasks->isEmpty()) {
            $this->info('No tasks found to check.');
            if ($delayExpiredCount > 0) {
                $this->info("  - Delays expired and notifications sent: {$delayExpiredCount}");
            }
            return 0;
        }

        $this->info("Found {$tasks->count()} tasks to check.");

        foreach ($tasks as $task) {
            try {
                // Check if there are active delays for this task (rest_max > 0)
                $activeDelays = Delay::where('task_id', (string)$task->id)
                    ->where('rest_max', '>', 0)
                    ->exists();

                if ($activeDelays) {
                    $this->line("Task #{$task->id} ({$task->name}): Active delay exists. Skipping.");
                    $skippedCount++;
                    continue;
                }

                $timeoutDateTime = $task->calculateTimeoutDateTime();

                if (!$timeoutDateTime) {
                    $this->warn("Task #{$task->id} ({$task->name}): Could not calculate timeout datetime.");
                    $skippedCount++;
                    continue;
                }

                // Check if timeout has been reached
                if ($now->gte($timeoutDateTime)) {
                    // Only send if not already notified
                    if (!$task->timeout_notified_at) {
                        $this->info("Task #{$task->id} ({$task->name}): Timeout reached. Sending notification...");

                        // Send notification to all assigned users and create notification_timeout records
                        $this->sendTimeoutNotification($task);

                        // Mark as notified
                        $task->timeout_notified_at = $now;
                        $task->save();

                        $notifiedCount++;
                        $this->info("  ✓ Notification sent and task marked as notified.");
                    }
                } else {
                    $timeRemaining = $now->diffForHumans($timeoutDateTime, true);
                    $this->line("Task #{$task->id} ({$task->name}): Timeout not reached yet. Time remaining: {$timeRemaining}");
                }
            } catch (\Exception $e) {
                $this->error("Error processing task #{$task->id}: " . $e->getMessage());
                Log::error('Error checking task timeout', [
                    'task_id' => $task->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                $skippedCount++;
            }
        }

        $this->newLine();
        $this->info("Summary:");
        $this->info("  - New notifications sent: {$notifiedCount}");
        $this->info("  - Delays expired and notifications sent: {$delayExpiredCount}");
        $this->info("  - Skipped/Errors: {$skippedCount}");
        $this->info("  - Total checked: {$tasks->count()}");

        return 0;
    }

    /**
     * Initialize Firebase messaging (lazy loading)
     */
    private function getMessaging()
    {
        if ($this->messaging !== null) {
            return $this->messaging;
        }

        try {
            $firebaseConfig = config('services.firebase');

            if (!$firebaseConfig || empty($firebaseConfig['project_id'])) {
                Log::warning('Firebase configuration is missing');
                return null;
            }

            // Prepare Firebase credentials
            $privateKey = $firebaseConfig['private_key'] ?? '';
            if (strpos($privateKey, '\\n') !== false) {
                $privateKey = str_replace('\\n', "\n", $privateKey);
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

            if (empty($credentials['private_key']) || empty($credentials['client_email'])) {
                Log::error('Firebase private_key or client_email is missing');
                return null;
            }

            // Create temporary JSON file for Firebase credentials
            $tempFile = tempnam(sys_get_temp_dir(), 'firebase_credentials_');
            file_put_contents($tempFile, json_encode($credentials));

            $factory = (new Factory)->withServiceAccount($tempFile);
            $this->messaging = $factory->createMessaging();

            // Clean up temp file
            @unlink($tempFile);

            Log::info('Firebase messaging initialized successfully in CheckTaskTimeouts');
            return $this->messaging;
        } catch (\Exception $e) {
            Log::error('Firebase initialization error in CheckTaskTimeouts: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Send timeout notification to assigned users
     */
    private function sendTimeoutNotification(Task $task): void
    {
        try {
            // Parse users field (JSON string like "[2]" or "[2,3]" or "[\"2\"]")
            if (empty($task->users)) {
                Log::info('Task has no assigned users, skipping timeout notification', ['task_id' => $task->id]);
                return;
            }

            $usersStr = $task->users;
            $userIds = [];

            // Try to decode as JSON array
            $usersArray = json_decode($usersStr, true);
            if (is_array($usersArray)) {
                // Convert all values to integers
                $userIds = array_map('intval', $usersArray);
            } else {
                // Fallback: try to extract IDs from string
                preg_match_all('/["\']?(\d+)["\']?/', $usersStr, $matches);
                if (!empty($matches[1])) {
                    $userIds = array_map('intval', $matches[1]);
                }
            }

            if (empty($userIds)) {
                Log::info('No valid user IDs found in task users field', [
                    'task_id' => $task->id,
                    'users_field' => $usersStr
                ]);
                return;
            }

            // Get users with FCM tokens
            $users = User::whereIn('id', $userIds)
                ->whereNotNull('fcm_token')
                ->where('fcm_token', '!=', '')
                ->get();

            if ($users->isEmpty()) {
                Log::info('No users with FCM tokens found for task', [
                    'task_id' => $task->id,
                    'user_ids' => $userIds
                ]);
                return;
            }

            // Initialize Firebase messaging
            $messaging = $this->getMessaging();
            if (!$messaging) {
                Log::warning('Firebase messaging not available, cannot send timeout notifications', [
                    'task_id' => $task->id
                ]);
                return;
            }

            // Calculate time remaining until time_cloture using the new time format
            $endDateTime = $task->calculateEndDateTime();
            $timeRemaining = $endDateTime ? Carbon::now()->diffForHumans($endDateTime, true) : 'unknown';

            // Create notification
            $title = "Task Timeout Alert: {$task->name}";
            $body = "The timeout for task '{$task->name}' has been reached. Time remaining until closure: {$timeRemaining}.";
            
            $notification = Notification::create($title, $body);

            $successCount = 0;
            $failedCount = 0;

            // Get rest_time and rest_max from task
            $restTime = $task->rest_time;
            $restMax = $task->rest_max ?? 0;

            // Send notification to each user and create records
            foreach ($users as $user) {
                try {
                    // Create notification_timeout record
                    NotificationTimeout::create([
                        'task_id' => (string)$task->id,
                        'users_id' => (string)$user->id,
                        'description' => $body,
                    ]);

                    // Create or update delay record with rest_time and rest_max from task
                    $delay = Delay::firstOrNew([
                        'user_id' => (string)$user->id,
                        'task_id' => (string)$task->id,
                    ]);
                    
                    if ($restTime) {
                        $delay->rest_time = Carbon::parse($restTime);
                    }
                    $delay->rest_max = $restMax;
                    $delay->save();

                    // Check if rest_max is 1 and inform user it's the last time
                    $isLastTime = ($delay->rest_max == 1);
                    $notificationBody = $body;
                    if ($isLastTime) {
                        $notificationBody = "⚠️ LAST TIME: {$body} This is your last rest/delay opportunity.";
                    }

                    $message = CloudMessage::withTarget('token', $user->fcm_token)
                        ->withNotification(Notification::create($title, $notificationBody))
                        ->withData([
                            'title' => $title,
                            'body' => $notificationBody,
                            'task_id' => $task->id,
                            'task_name' => $task->name,
                            'task_step' => $task->step,
                            'user_name' => $user->user_name,
                            'notification_type' => 'timeout',
                            'is_last_time' => $isLastTime,
                        ]);

                    $messaging->send($message);
                    $successCount++;

                    Log::info('Timeout notification sent to user', [
                        'user_id' => $user->id,
                        'user_name' => $user->user_name,
                        'task_id' => $task->id,
                        'rest_max' => $delay->rest_max,
                        'is_last_time' => $isLastTime,
                    ]);
                } catch (\Exception $e) {
                    $failedCount++;
                    Log::error('Failed to send timeout notification to user', [
                        'user_id' => $user->id,
                        'user_name' => $user->user_name,
                        'task_id' => $task->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            Log::info('Task timeout notifications sent', [
                'task_id' => $task->id,
                'task_name' => $task->name,
                'success_count' => $successCount,
                'failed_count' => $failedCount,
                'total_users' => $users->count(),
            ]);
        } catch (\Exception $e) {
            Log::error('Error sending task timeout notifications', [
                'task_id' => $task->id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Send timeout notification to a specific user (used when delay expires)
     */
    private function sendTimeoutNotificationToUser(Task $task, int $userId): void
    {
        try {
            $user = User::where('id', $userId)
                ->whereNotNull('fcm_token')
                ->where('fcm_token', '!=', '')
                ->first();

            if (!$user) {
                Log::info('User not found or has no FCM token', [
                    'user_id' => $userId,
                    'task_id' => $task->id
                ]);
                return;
            }

            // Initialize Firebase messaging
            $messaging = $this->getMessaging();
            if (!$messaging) {
                Log::warning('Firebase messaging not available, cannot send timeout notification', [
                    'task_id' => $task->id,
                    'user_id' => $userId
                ]);
                return;
            }

            // Calculate time remaining until time_cloture using the new time format
            $endDateTime = $task->calculateEndDateTime();
            $timeRemaining = $endDateTime ? Carbon::now()->diffForHumans($endDateTime, true) : 'unknown';

            // Create notification
            $title = "Task Timeout Alert: {$task->name}";
            $body = "The delay has expired. The timeout for task '{$task->name}' has been reached. Time remaining until closure: {$timeRemaining}.";
            
            $notification = Notification::create($title, $body);

            try {
                $message = CloudMessage::withTarget('token', $user->fcm_token)
                    ->withNotification($notification)
                    ->withData([
                        'title' => $title,
                        'body' => $body,
                        'task_id' => $task->id,
                        'task_name' => $task->name,
                        'task_step' => $task->step,
                        'user_name' => $user->user_name,
                        'notification_type' => 'timeout',
                    ]);

                $messaging->send($message);

                Log::info('Timeout notification sent to user after delay expiration', [
                    'user_id' => $user->id,
                    'user_name' => $user->user_name,
                    'task_id' => $task->id,
                ]);
            } catch (\Exception $e) {
                Log::error('Failed to send timeout notification to user after delay expiration', [
                    'user_id' => $user->id,
                    'user_name' => $user->user_name,
                    'task_id' => $task->id,
                    'error' => $e->getMessage(),
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Error sending timeout notification to user', [
                'user_id' => $userId,
                'task_id' => $task->id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
