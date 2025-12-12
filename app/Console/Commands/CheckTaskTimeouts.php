<?php

namespace App\Console\Commands;

use App\Models\Task;
use App\Models\User;
use App\Models\Delay;
use App\Models\NotificationTimeout;
use App\Models\AlarmNotification;
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
        $this->info('Checking for task start times and delay alarms...');

        $now = Carbon::now();
        $notifiedCount = 0;
        $skippedCount = 0;
        $delayAlarmCount = 0;
        $repeatTimeoutCount = 0;
        $alarmNotificationCount = 0;

        // First, check for alarm notifications that need to be sent
        $this->info('Checking for alarm notifications...');
        $this->processAlarmNotifications($now, $alarmNotificationCount, $skippedCount);

        // Then, check for active delays that need repeat alarms
        $this->info('Checking for delay repeat alarms...');
        $activeDelays = Delay::where('rest_max', '>', 0)
            ->whereNotNull('next_alarm_at')
            ->where('next_alarm_at', '<=', $now)
            ->get();

        foreach ($activeDelays as $delay) {
            try {
                $task = Task::find($delay->task_id);
                if (!$task) {
                    continue;
                }

                $user = User::find($delay->user_id);
                if (!$user || !$user->fcm_token) {
                    continue;
                }

                // Check if rest_max is 1 (last time)
                $isLastTime = ($delay->rest_max == 1);

                $this->info("Sending repeat alarm for delay #{$delay->id} (Task: {$task->name}, User: {$user->user_name})");

                // Send repeat alarm notification
                $this->sendDelayRepeatAlarm($task, $user, $delay, $isLastTime);

                // Update alarm tracking
                $delay->alarm_count++;
                $delay->last_alarm_at = $now;

                // Calculate next alarm time based on rest_time
                if ($delay->rest_time) {
                    $restTimeStr = $delay->rest_time;
                    if (is_object($restTimeStr)) {
                        // If it's a Carbon instance, get time string
                        if (method_exists($restTimeStr, 'format')) {
                            $restTimeStr = $restTimeStr->format('H:i:s');
                        } else {
                            // If it's already a time string from database
                            $restTimeStr = (string)$restTimeStr;
                        }
                    }

                    // Parse rest_time (HH:mm:ss format)
                    $restTimeParts = explode(':', $restTimeStr);
                    if (count($restTimeParts) >= 2) {
                        $hours = (int)$restTimeParts[0];
                        $minutes = (int)$restTimeParts[1];
                        $seconds = isset($restTimeParts[2]) ? (int)$restTimeParts[2] : 0;

                        // Set next alarm time from now
                        $delay->next_alarm_at = $now->copy()->addHours($hours)->addMinutes($minutes)->addSeconds($seconds);
                    }
                }

                $delay->save();
                $delayAlarmCount++;

            } catch (\Exception $e) {
                $this->error("Error processing delay #{$delay->id}: " . $e->getMessage());
                Log::error('Error processing delay repeat alarm', [
                    'delay_id' => $delay->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Then process notification_timeout repeats based on `next` field
        $this->info('Checking for notification timeout repeats...');
        $this->processNotificationTimeoutRepeats($now, $repeatTimeoutCount, $skippedCount);

        // Now check for tasks that have reached their start time (time_out)
        $this->info('Checking for task start times...');

        // Get all tasks with time_out set (start time)
        $tasks = Task::whereNotNull('time_out')
            ->where('status', true) // Only active tasks
            ->get()
            ->filter(function ($task) use ($now) {
                // Only process if not already notified OR if there are no active delays
                if ($task->timeout_notified_at) {
                    // Check if there are any active delays for this task (rest_max > 0)
                    $activeDelays = Delay::where('task_id', (string)$task->id)
                        ->where('rest_max', '>', 0)
                        ->exists();
                    
                    // If there are active delays, don't send initial notification again
                    return !$activeDelays;
                }
                return true;
            });

        if ($tasks->isEmpty()) {
            $this->info('No tasks found to check for start time.');
        } else {
            $this->info("Found {$tasks->count()} tasks to check for start time.");

            foreach ($tasks as $task) {
                try {
                    // Check if there are active delays for this task (rest_max > 0)
                    $activeDelays = Delay::where('task_id', (string)$task->id)
                        ->where('rest_max', '>', 0)
                        ->exists();

                    if ($activeDelays) {
                        $this->line("Task #{$task->id} ({$task->name}): Active delay exists. Skipping initial notification.");
                        $skippedCount++;
                        continue;
                    }

                    // Calculate start datetime from time_out
                    $startDateTime = $task->calculateTimeoutDateTime();

                    if (!$startDateTime) {
                        $this->warn("Task #{$task->id} ({$task->name}): Could not calculate start datetime.");
                        $skippedCount++;
                        continue;
                    }

                    // Check if start time has been reached (within current minute)
                    if ($now->gte($startDateTime) && $now->diffInMinutes($startDateTime) < 1) {
                        // Only send if not already notified
                        if (!$task->timeout_notified_at) {
                            $this->info("Task #{$task->id} ({$task->name}): Start time reached. Sending notification...");

                            // Send notification to all assigned users
                            $this->sendStartTimeNotification($task);

                            // Mark as notified
                            $task->timeout_notified_at = $now;
                            $task->save();

                            $notifiedCount++;
                            $this->info("  ✓ Notification sent and task marked as notified.");
                        }
                    } else {
                        $timeRemaining = $now->diffForHumans($startDateTime, true);
                        $this->line("Task #{$task->id} ({$task->name}): Start time not reached yet. Time remaining: {$timeRemaining}");
                    }
                } catch (\Exception $e) {
                    $this->error("Error processing task #{$task->id}: " . $e->getMessage());
                    Log::error('Error checking task start time', [
                        'task_id' => $task->id,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                    $skippedCount++;
                }
            }
        }

        $this->newLine();
        $this->info("Summary:");
        $this->info("  - New start time notifications sent: {$notifiedCount}");
        $this->info("  - Alarm notifications sent: {$alarmNotificationCount}");
        $this->info("  - Delay repeat alarms sent: {$delayAlarmCount}");
        $this->info("  - Timeout repeat notifications sent: {$repeatTimeoutCount}");
        $this->info("  - Skipped/Errors: {$skippedCount}");

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
     * Send repeat timeout notification based on notification_timeout record
     */
    private function sendRepeatTimeoutNotification(NotificationTimeout $notificationTimeout, Task $task, User $user): void
    {
        try {
            // Initialize Firebase messaging
            $messaging = $this->getMessaging();
            if (!$messaging) {
                Log::warning('Firebase messaging not available, cannot send repeat timeout notification', [
                    'task_id' => $task->id,
                    'user_id' => $user->id,
                    'notification_timeout_id' => $notificationTimeout->id,
                ]);
                return;
            }

            // Calculate time remaining until time_cloture
            $endDateTime = $task->calculateEndDateTime();
            $timeRemaining = $endDateTime ? Carbon::now()->diffForHumans($endDateTime, true) : 'unknown';

            // Determine if this is the last allowed repeat and which number this send is
            $restMax = (int)($notificationTimeout->rest_max ?? 0);
            $repeatCount = (int)($notificationTimeout->repeat_count ?? 0);
            $sendNumber = $repeatCount + 1; // 1 = first repeat after initial, 2 = second repeat, etc.
            $isLastTime = $restMax > 0 && $sendNumber >= $restMax;

            // Create notification title/body
            $baseTitle = "Task Timeout Reminder: {$task->name}";
            $baseBody = "Reminder: The timeout for task '{$task->name}' is active. Time remaining until closure: {$timeRemaining}.";

            if ($isLastTime) {
                $title = "⚠️ LAST TIME - {$baseTitle}";
                $body = "{$baseBody} This is your last timeout reminder.";
            } else {
                $title = $baseTitle;
                // Add info about which repeat this is, starting from 2nd notification
                if ($sendNumber > 1) {
                    $body = "{$baseBody} This is your {$sendNumber} time timeout reminder.";
                } else {
                    $body = $baseBody;
                }
            }

            $notification = Notification::create($title, $body);

            $message = CloudMessage::withTarget('token', $user->fcm_token)
                ->withNotification($notification)
                ->withData([
                    'title' => $title,
                    'body' => $body,
                    'task_id' => $task->id,
                    'task_name' => $task->name,
                    'task_step' => $task->step,
                    'user_name' => $user->user_name,
                    'notification_type' => 'timeout_repeat',
                    'notification_timeout_id' => $notificationTimeout->id,
                    'is_last_time' => $isLastTime,
                    'rest_max' => $restMax,
                    'repeat_count' => $sendNumber,
                    'send_number' => $sendNumber,
                ]);

            $messaging->send($message);

            Log::info('Repeat timeout notification sent to user', [
                'user_id' => $user->id,
                'user_name' => $user->user_name,
                'task_id' => $task->id,
                'notification_timeout_id' => $notificationTimeout->id,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send repeat timeout notification', [
                'user_id' => $user->id ?? null,
                'task_id' => $task->id ?? null,
                'notification_timeout_id' => $notificationTimeout->id ?? null,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Send start time notification to assigned users
     */
    private function sendStartTimeNotification(Task $task): void
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

            // Exclude creator and controller from notifications
            $excludedUserIds = [];
            
            // Exclude creator (created_by field)
            if (!empty($task->created_by)) {
                $excludedUserIds[] = (int)$task->created_by;
            }
            
            // Exclude controller (controller field - can be ID, user_name, or email)
            if (!empty($task->controller)) {
                $controller = $task->controller;
                $controllerUser = null;
                
                // Try to find controller by ID first
                if (is_numeric($controller)) {
                    $controllerUser = User::where('id', (int)$controller)->first();
                }
                
                // If not found by ID, try by user_name or email
                if (!$controllerUser) {
                    $controllerUser = User::where(function ($query) use ($controller) {
                        $query->where('user_name', $controller)
                            ->orWhere('email', $controller);
                    })->first();
                }
                
                if ($controllerUser) {
                    $excludedUserIds[] = $controllerUser->id;
                }
            }
            
            // Remove excluded users from the list
            $userIds = array_diff($userIds, $excludedUserIds);
            
            if (empty($userIds)) {
                Log::info('No users to notify after excluding creator and controller', [
                    'task_id' => $task->id,
                    'excluded_user_ids' => $excludedUserIds
                ]);
                return;
            }

            // Get users with FCM tokens (excluding creator and controller)
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

            // Create notification for task start time
            $title = "Task Start Time: {$task->name}";
            $body = "The start time for task '{$task->name}' has been reached. Time remaining until closure: {$timeRemaining}.";
            
            $notification = Notification::create($title, $body);

            $successCount = 0;
            $failedCount = 0;

            // Get rest_time and rest_max from task
            $restTime = $task->rest_time;
            $restMax = $task->rest_max ?? 0;
            $restTimeStr = null;

            if ($restTime) {
                $restTimeStr = $restTime;
                if (is_object($restTimeStr)) {
                    // If it's a Carbon instance, get time string
                    if (method_exists($restTimeStr, 'format')) {
                        $restTimeStr = $restTimeStr->format('H:i:s');
                    } else {
                        $restTimeStr = (string)$restTimeStr;
                    }
                }
            }

            // Send notification to each user and create records
            foreach ($users as $user) {
                try {
                    // Calculate next notification datetime based on rest_time and rest_max
                    $nextNotificationAt = null;
                    if ($restTimeStr && $restMax > 0) {
                        $restTimeParts = explode(':', $restTimeStr);
                        if (count($restTimeParts) >= 2) {
                            $hours = (int)$restTimeParts[0];
                            $minutes = (int)$restTimeParts[1];
                            $seconds = isset($restTimeParts[2]) ? (int)$restTimeParts[2] : 0;

                            $nextNotificationAt = Carbon::now()
                                ->copy()
                                ->addHours($hours)
                                ->addMinutes($minutes)
                                ->addSeconds($seconds);
                        }
                    }

                    // Build a richer description stored in DB
                    $dbDescriptionLines = [
                        "Start timeout notification created.",
                        "Task: {$task->name} (ID: {$task->id})",
                        "User: {$user->user_name} (ID: {$user->id})",
                        "Time remaining until closure: {$timeRemaining}",
                        "Rest time between timeout notifications: " . ($restTimeStr ?: 'none'),
                        "Max repeats (rest_max): " . (int)$restMax,
                        "Next notification at: " . ($nextNotificationAt ? $nextNotificationAt->toDateTimeString() : 'none'),
                    ];
                    $dbDescription = implode("\n", $dbDescriptionLines);

                    // Create notification_timeout record
                    NotificationTimeout::create([
                        'task_id' => (string)$task->id,
                        'users_id' => (string)$user->id,
                        'description' => $dbDescription,
                        'next' => $nextNotificationAt,
                        'rest_max' => (int)$restMax,
                        'repeat_count' => 0,
                        'read' => 0,
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
                            'notification_type' => 'start_time',
                            'is_last_time' => $isLastTime,
                            'rest_max' => $delay->rest_max,
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
     * Send repeat alarm notification for delay
     */
    private function sendDelayRepeatAlarm(Task $task, User $user, Delay $delay, bool $isLastTime): void
    {
        try {
            // Initialize Firebase messaging
            $messaging = $this->getMessaging();
            if (!$messaging) {
                Log::warning('Firebase messaging not available, cannot send delay repeat alarm', [
                    'task_id' => $task->id,
                    'user_id' => $user->id
                ]);
                return;
            }

            // Calculate time remaining until time_cloture
            $endDateTime = $task->calculateEndDateTime();
            $timeRemaining = $endDateTime ? Carbon::now()->diffForHumans($endDateTime, true) : 'unknown';

            // Create notification
            $title = "Task Reminder: {$task->name}";
            $body = "Reminder: Task '{$task->name}' is still active. Time remaining until closure: {$timeRemaining}.";
            
            if ($isLastTime) {
                $title = "⚠️ LAST TIME - Task Reminder: {$task->name}";
                $body = "⚠️ LAST TIME: This is your final reminder for task '{$task->name}'. Time remaining until closure: {$timeRemaining}.";
            }

            $notification = Notification::create($title, $body);

            $message = CloudMessage::withTarget('token', $user->fcm_token)
                ->withNotification($notification)
                ->withData([
                    'title' => $title,
                    'body' => $body,
                    'task_id' => $task->id,
                    'task_name' => $task->name,
                    'task_step' => $task->step,
                    'user_name' => $user->user_name,
                    'notification_type' => 'delay_repeat_alarm',
                    'is_last_time' => $isLastTime,
                    'rest_max' => $delay->rest_max,
                    'alarm_count' => $delay->alarm_count + 1,
                ]);

            $messaging->send($message);

            Log::info('Delay repeat alarm sent to user', [
                'user_id' => $user->id,
                'user_name' => $user->user_name,
                'task_id' => $task->id,
                'delay_id' => $delay->id,
                'rest_max' => $delay->rest_max,
                'alarm_count' => $delay->alarm_count + 1,
                'is_last_time' => $isLastTime,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send delay repeat alarm', [
                'user_id' => $user->id,
                'user_name' => $user->user_name,
                'task_id' => $task->id,
                'delay_id' => $delay->id,
                'error' => $e->getMessage(),
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

    /**
     * Process notification_timeout records whose next time has passed
     */
    private function processNotificationTimeoutRepeats(Carbon $now, int &$repeatTimeoutCount, int &$skippedCount): void
    {
        $dueNotifications = NotificationTimeout::whereNotNull('next')
            ->where('next', '<=', $now)
            ->get();

        if ($dueNotifications->isEmpty()) {
            return;
        }

        foreach ($dueNotifications as $notificationTimeout) {
            try {
                $task = Task::find($notificationTimeout->task_id);
                if (!$task) {
                    $skippedCount++;
                    continue;
                }

                $user = User::where('id', $notificationTimeout->users_id)
                    ->whereNotNull('fcm_token')
                    ->where('fcm_token', '!=', '')
                    ->first();

                if (!$user) {
                    $skippedCount++;
                    continue;
                }

                // Check rest_max / repeat_count limits
                $restMax = (int)($notificationTimeout->rest_max ?? 0);
                $repeatCount = (int)($notificationTimeout->repeat_count ?? 0);

                if ($restMax > 0 && $repeatCount >= $restMax) {
                    // No more repeats allowed
                    $notificationTimeout->next = null;
                    $notificationTimeout->save();
                    $skippedCount++;
                    continue;
                }

                // Send repeat notification
                $this->sendRepeatTimeoutNotification($notificationTimeout, $task, $user);
                $repeatTimeoutCount++;

                // This send number (human readable) and updated repeat count
                $sendNumber = $repeatCount + 1;
                $newRepeatCount = $repeatCount + 1;

                // Mark current record as processed history
                $notificationTimeout->repeat_count = $newRepeatCount;
                $notificationTimeout->next = null;

                $isLastTime = $restMax > 0 && $newRepeatCount >= $restMax;
                $remaining = $restMax > 0
                    ? max($restMax - $newRepeatCount, 0)
                    : null;

                $descLines = [
                    "Timeout repeat notification sent.",
                    "Task: {$task->name} (ID: {$task->id})",
                    "User: {$user->user_name} (ID: {$user->id})",
                    "This was notification number: {$sendNumber}" . ($restMax > 0 ? " of {$restMax}" : ''),
                    $isLastTime
                        ? "This was the last allowed timeout repeat notification."
                        : ($remaining !== null ? "Remaining allowed repeats: {$remaining}." : "No repeat limit configured (rest_max = 0)."),
                    "Processed at: " . Carbon::now()->toDateTimeString(),
                ];
                $notificationTimeout->description = implode("\n", $descLines);
                $notificationTimeout->save();

                // If more repeats are allowed, create a NEW notification_timeout row for the next datetime
                $restTime = $task->rest_time;
                if (!$isLastTime && $restTime) {
                    $restTimeStr = $restTime;
                    if (is_object($restTimeStr)) {
                        if (method_exists($restTimeStr, 'format')) {
                            $restTimeStr = $restTimeStr->format('H:i:s');
                        } else {
                            $restTimeStr = (string)$restTimeStr;
                        }
                    }

                    $nextNotificationAt = null;
                    $restTimeParts = explode(':', $restTimeStr);
                    if (count($restTimeParts) >= 2) {
                        $hours = (int)$restTimeParts[0];
                        $minutes = (int)$restTimeParts[1];
                        $seconds = isset($restTimeParts[2]) ? (int)$restTimeParts[2] : 0;

                        $nextNotificationAt = $now->copy()
                            ->addHours($hours)
                            ->addMinutes($minutes)
                            ->addSeconds($seconds);
                    }

                    if ($nextNotificationAt) {
                        $scheduleLines = [
                            "Scheduled timeout repeat notification.",
                            "Task: {$task->name} (ID: {$task->id})",
                            "User: {$user->user_name} (ID: {$user->id})",
                            "This will be notification number: " . ($newRepeatCount + 1) . ($restMax > 0 ? " of {$restMax}" : ''),
                            "Scheduled at: " . $nextNotificationAt->toDateTimeString(),
                            "Created at: " . Carbon::now()->toDateTimeString(),
                        ];

                        NotificationTimeout::create([
                            'task_id' => (string)$task->id,
                            'users_id' => (string)$user->id,
                            'description' => implode("\n", $scheduleLines),
                            'next' => $nextNotificationAt,
                            'rest_max' => $restMax,
                            'repeat_count' => $newRepeatCount,
                            'read' => 0,
                        ]);
                    }
                }
            } catch (\Exception $e) {
                $skippedCount++;
                Log::error('Error processing notification_timeout repeat', [
                    'notification_timeout_id' => $notificationTimeout->id ?? null,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Process alarm notifications - check for tasks where alarm start time has been reached
     */
    private function processAlarmNotifications(Carbon $now, int &$alarmNotificationCount, int &$skippedCount): void
    {
        // First, process all due alarm notifications (based on 'next' field)
        // This handles all scheduled subsequent notifications
        $allDueAlarmNotifications = AlarmNotification::whereNotNull('next')
            ->where('next', '<=', $now)
            ->get()
            ->groupBy('task_id');

        if ($allDueAlarmNotifications->isNotEmpty()) {
            $this->info("Found " . $allDueAlarmNotifications->count() . " task(s) with due alarm notifications to process.");
        }

        foreach ($allDueAlarmNotifications as $taskId => $alarmNotifications) {
            try {
                $task = Task::find($taskId);
                if (!$task || !$task->status) {
                    continue;
                }

                $this->info("Processing due alarm notifications for task #{$taskId} ({$task->name})");
                $this->processDueAlarmNotifications($task, $now, $alarmNotificationCount, $skippedCount);
            } catch (\Exception $e) {
                $this->error("Error processing due alarm notifications for task #{$taskId}: " . $e->getMessage());
                Log::error('Error processing due alarm notifications', [
                    'task_id' => $taskId,
                    'error' => $e->getMessage(),
                ]);
                $skippedCount++;
            }
        }

        // Then, check for tasks where alarm start time has been reached but not yet initialized
        // Alarm works only in timeout:
        // - For pending tasks: send to assigned users
        // - For in_progress tasks: send to controller
        // - For completed tasks: don't send any notifications
        $tasks = Task::whereNotNull('alarm')
            ->whereNotNull('time_cloture')
            ->where('status', true) // Only active tasks
            ->whereIn('step', ['pending', 'in_progress']) // Only pending or in_progress tasks
            ->get();

        if ($tasks->isEmpty()) {
            return;
        }

        foreach ($tasks as $task) {
            try {
                // Skip completed tasks - no alarm notifications for completed tasks
                if ($task->step === 'completed') {
                    continue;
                }

                // Calculate alarm start time
                $alarmStartTime = $task->calculateAlarmStartTime();
                
                if (!$alarmStartTime) {
                    continue;
                }

                // Check if alarm start time has been reached
                if ($now->gte($alarmStartTime)) {
                    // Check if alarm notifications have already been initialized
                    $existingAlarmNotifications = AlarmNotification::where('task_id', (string)$task->id)->get();
                    
                    if ($existingAlarmNotifications->isEmpty()) {
                        // Initialize alarm notifications based on task step
                        $this->info("Initializing alarm notifications for task #{$task->id} ({$task->name}) - Step: {$task->step}");
                        $this->initializeAlarmNotifications($task, $now);
                    }
                }
            } catch (\Exception $e) {
                $this->error("Error processing alarm for task #{$task->id}: " . $e->getMessage());
                Log::error('Error processing alarm notification', [
                    'task_id' => $task->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                $skippedCount++;
            }
        }
    }

    /**
     * Initialize alarm notifications when alarm start time is reached
     * 
     * Alarm works only in timeout:
     * - For pending tasks: send to assigned users
     * - For in_progress tasks: send to controller
     * - For completed tasks: don't send any notifications (should not reach here)
     * 
     * Notification rules:
     * - All user types (super_admin, admin, user) can receive notifications
     * - Creator is excluded from notifications
     */
    private function initializeAlarmNotifications(Task $task, Carbon $now): void
    {
        try {
            // Don't send alarm notifications for completed tasks
            if ($task->step === 'completed') {
                Log::info('Task is completed, skipping alarm notification', ['task_id' => $task->id]);
                return;
            }

            // Determine who should receive notifications based on task step
            $usersToNotify = [];
            
            if ($task->step === 'pending') {
                // For pending tasks: send to assigned users
                if (empty($task->users)) {
                    Log::info('Task has no assigned users for alarm notification', ['task_id' => $task->id]);
                    return;
                }
                
                // Parse users field
                $usersStr = $task->users;
                $userIds = [];

                // Try to decode as JSON array
                $usersArray = json_decode($usersStr, true);
                if (is_array($usersArray)) {
                    $userIds = array_map('intval', $usersArray);
                } else {
                    preg_match_all('/["\']?(\d+)["\']?/', $usersStr, $matches);
                    if (!empty($matches[1])) {
                        $userIds = array_map('intval', $matches[1]);
                    }
                }

                if (empty($userIds)) {
                    return;
                }

                // Exclude creator
                $excludedUserIds = [];
                if (!empty($task->created_by)) {
                    $excludedUserIds[] = (int)$task->created_by;
                }
                $userIds = array_diff($userIds, $excludedUserIds);

                if (empty($userIds)) {
                    return;
                }

                // Get users with FCM tokens
                $usersToNotify = User::whereIn('id', $userIds)
                    ->whereNotNull('fcm_token')
                    ->where('fcm_token', '!=', '')
                    ->get();
                    
            } elseif ($task->step === 'in_progress') {
                // For in_progress tasks: send to controller only
                if (empty($task->controller)) {
                    Log::info('Task is in_progress but has no controller for alarm notification', ['task_id' => $task->id]);
                    return;
                }
                
                // Find controller user
                $controller = $task->controller;
                $controllerUser = null;
                if (is_numeric($controller)) {
                    $controllerUser = User::where('id', (int)$controller)
                        ->whereNotNull('fcm_token')
                        ->where('fcm_token', '!=', '')
                        ->first();
                }
                if (!$controllerUser) {
                    $controllerUser = User::where(function ($query) use ($controller) {
                        $query->where('user_name', $controller)
                            ->orWhere('email', $controller);
                    })
                    ->whereNotNull('fcm_token')
                    ->where('fcm_token', '!=', '')
                    ->first();
                }
                
                if ($controllerUser) {
                    $usersToNotify = collect([$controllerUser]);
                }
            }

            if ($usersToNotify->isEmpty()) {
                return;
            }

            // Use the users we determined above (assigned users for pending, controller for in_progress)
            $users = $usersToNotify;

            // Get rest_time and rest_max from task
            $restTime = $task->rest_time;
            $restMax = (int)($task->rest_max ?? 0);
            $restTimeStr = null;

            if ($restTime) {
                $restTimeStr = $restTime;
                if (is_object($restTimeStr) && method_exists($restTimeStr, 'format')) {
                    $restTimeStr = $restTimeStr->format('H:i:s');
                } else {
                    $restTimeStr = (string)$restTimeStr;
                }
            }

            // Calculate next alarm time (only if there are more notifications to send)
            $nextAlarmAt = null;
            if ($restTimeStr && $restMax > 1) { // Only schedule next if restMax > 1 (more than just the first notification)
                $restTimeParts = explode(':', $restTimeStr);
                if (count($restTimeParts) >= 2) {
                    $hours = (int)$restTimeParts[0];
                    $minutes = (int)$restTimeParts[1];
                    $seconds = isset($restTimeParts[2]) ? (int)$restTimeParts[2] : 0;
                    $nextAlarmAt = $now->copy()
                        ->addHours($hours)
                        ->addMinutes($minutes)
                        ->addSeconds($seconds);
                }
            }

            // Create alarm notification records for each user and send first notification
            foreach ($users as $user) {
                try {
                    // Create alarm notification record
                    $alarmNotification = AlarmNotification::create([
                        'task_id' => (string)$task->id,
                        'users_id' => (string)$user->id,
                        'description' => "Alarm notification initialized for task: {$task->name}",
                        'next' => $nextAlarmAt,
                        'rest_max' => $restMax,
                        'notification_count' => 0,
                        'read' => 0,
                    ]);

                    // Send first alarm notification
                    $this->sendAlarmNotification($task, $user, $alarmNotification, $now);
                    
                    // Increment notification count after sending first notification
                    $alarmNotification->notification_count = 1;
                    $alarmNotification->save();

                } catch (\Exception $e) {
                    Log::error('Error initializing alarm notification for user', [
                        'user_id' => $user->id,
                        'task_id' => $task->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        } catch (\Exception $e) {
            Log::error('Error initializing alarm notifications', [
                'task_id' => $task->id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Process due alarm notifications
     */
    private function processDueAlarmNotifications(Task $task, Carbon $now, int &$alarmNotificationCount, int &$skippedCount): void
    {
        // Don't send alarm notifications for completed tasks
        if ($task->step === 'completed') {
            $this->info("  Task #{$task->id} is completed, skipping alarm notifications");
            // Mark all pending alarm notifications as complete
            AlarmNotification::where('task_id', (string)$task->id)
                ->whereNotNull('next')
                ->update(['next' => null]);
            return;
        }

        // Get all due alarm notifications for this task
        $dueAlarmNotifications = AlarmNotification::where('task_id', (string)$task->id)
            ->whereNotNull('next')
            ->where('next', '<=', $now)
            ->get();

        if ($dueAlarmNotifications->isEmpty()) {
            return;
        }

        $this->info("  Found {$dueAlarmNotifications->count()} due alarm notification(s) for task #{$task->id} (Step: {$task->step})");

        foreach ($dueAlarmNotifications as $alarmNotification) {
            try {
                // Verify that the user should still receive notifications based on task step
                $user = User::where('id', $alarmNotification->users_id)
                    ->whereNotNull('fcm_token')
                    ->where('fcm_token', '!=', '')
                    ->first();

                if (!$user) {
                    $skippedCount++;
                    continue;
                }

                // Check if user should receive notification based on task step
                $shouldReceiveNotification = false;
                
                if ($task->step === 'pending') {
                    // For pending tasks: only assigned users should receive notifications
                    $isAssignedToUser = $this->isTaskAssignedToUser($task, $user->id);
                    $shouldReceiveNotification = $isAssignedToUser;
                } elseif ($task->step === 'in_progress') {
                    // For in_progress tasks: only controller should receive notifications
                    $isController = !empty($task->controller) && 
                        ($task->controller == $user->id || 
                         $task->controller == $user->user_name ||
                         $task->controller == $user->email ||
                         strpos($task->controller, (string)$user->id) !== false);
                    $shouldReceiveNotification = $isController;
                }
                
                if (!$shouldReceiveNotification) {
                    // User should not receive notification for this step, skip and mark as complete
                    $alarmNotification->next = null;
                    $alarmNotification->save();
                    $skippedCount++;
                    continue;
                }

                // Check if max notifications reached
                $restMax = (int)($alarmNotification->rest_max ?? 0);
                $notificationCount = (int)($alarmNotification->notification_count ?? 0);

                if ($restMax > 0 && $notificationCount >= $restMax) {
                    // Max notifications reached, mark as complete
                    $alarmNotification->next = null;
                    $alarmNotification->save();
                    $skippedCount++;
                    continue;
                }

                // Send alarm notification
                $this->info("  Sending alarm notification #" . ($notificationCount + 1) . " to user {$user->user_name} (ID: {$user->id})");
                $this->sendAlarmNotification($task, $user, $alarmNotification, $now);
                $alarmNotificationCount++;

                // Update notification count
                $notificationCount++;
                $alarmNotification->notification_count = $notificationCount;

                // Calculate next alarm time
                $restTime = $task->rest_time;
                $nextAlarmAt = null;
                if ($restTime && $restMax > 0 && $notificationCount < $restMax) {
                    $restTimeStr = $restTime;
                    if (is_object($restTimeStr) && method_exists($restTimeStr, 'format')) {
                        $restTimeStr = $restTimeStr->format('H:i:s');
                    } else {
                        $restTimeStr = (string)$restTimeStr;
                    }

                    $restTimeParts = explode(':', $restTimeStr);
                    if (count($restTimeParts) >= 2) {
                        $hours = (int)$restTimeParts[0];
                        $minutes = (int)$restTimeParts[1];
                        $seconds = isset($restTimeParts[2]) ? (int)$restTimeParts[2] : 0;
                        $nextAlarmAt = $now->copy()
                            ->addHours($hours)
                            ->addMinutes($minutes)
                            ->addSeconds($seconds);
                    }
                }

                // Update alarm notification record
                $alarmNotification->next = $nextAlarmAt;
                $alarmNotification->save();

            } catch (\Exception $e) {
                $skippedCount++;
                Log::error('Error processing alarm notification', [
                    'alarm_notification_id' => $alarmNotification->id ?? null,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Send alarm notification to user
     */
    private function sendAlarmNotification(Task $task, User $user, AlarmNotification $alarmNotification, Carbon $now): void
    {
        try {
            // Initialize Firebase messaging
            $messaging = $this->getMessaging();
            if (!$messaging) {
                Log::warning('Firebase messaging not available, cannot send alarm notification', [
                    'task_id' => $task->id,
                    'user_id' => $user->id
                ]);
                return;
            }

            // Calculate time remaining until task end
            $endDateTime = $task->calculateEndDateTime();
            $timeRemaining = $endDateTime ? $now->diffForHumans($endDateTime, true) : 'unknown';

            // Get notification details
            $restMax = (int)($alarmNotification->rest_max ?? 0);
            $notificationCount = (int)($alarmNotification->notification_count ?? 0);
            $currentNotificationNumber = $notificationCount + 1;
            $notificationsLeft = $restMax > 0 ? max($restMax - $currentNotificationNumber, 0) : null;
            $isLastNotification = $restMax > 0 && $currentNotificationNumber >= $restMax;

            // Build notification message
            $title = "Task Alarm: {$task->name}";
            $body = "This is alarm notification #{$currentNotificationNumber}";
            
            if ($restMax > 0) {
                $body .= " of {$restMax}";
            }
            
            $body .= ". Time remaining until task end: {$timeRemaining}.";
            
            if ($notificationsLeft !== null && $notificationsLeft > 0) {
                $body .= " {$notificationsLeft} notification(s) remaining.";
            }
            
            if ($isLastNotification) {
                $title = "⚠️ LAST ALARM - Task Alarm: {$task->name}";
                $body = "⚠️ LAST ALARM: This is your final alarm notification (#{$currentNotificationNumber} of {$restMax}). Time remaining until task end: {$timeRemaining}.";
            }

            $notification = Notification::create($title, $body);

            $message = CloudMessage::withTarget('token', $user->fcm_token)
                ->withNotification($notification)
                ->withData([
                    'title' => $title,
                    'body' => $body,
                    'task_id' => $task->id,
                    'task_name' => $task->name,
                    'task_step' => $task->step,
                    'user_name' => $user->user_name,
                    'notification_type' => 'alarm',
                    'notification_number' => $currentNotificationNumber,
                    'total_notifications' => $restMax > 0 ? $restMax : null,
                    'notifications_left' => $notificationsLeft,
                    'is_last_notification' => $isLastNotification,
                    'time_remaining' => $timeRemaining,
                ]);

            $messaging->send($message);

            // Store notification in task_notifications table
            \App\Models\TaskNotification::create([
                'user_id' => $user->id,
                'task_id' => $task->id,
                'title' => $title,
                'body' => $body,
                'type' => 'alarm',
            ]);

            // Update alarm notification description
            $descLines = [
                "Alarm notification #{$currentNotificationNumber} sent.",
                "Task: {$task->name} (ID: {$task->id})",
                "User: {$user->user_name} (ID: {$user->id})",
                "Time remaining until task end: {$timeRemaining}",
                $restMax > 0 ? "Notification {$currentNotificationNumber} of {$restMax}" : "Notification #{$currentNotificationNumber}",
                $notificationsLeft !== null && $notificationsLeft > 0 ? "{$notificationsLeft} notification(s) remaining" : ($isLastNotification ? "This was the last alarm notification" : "No notification limit"),
                "Sent at: " . $now->toDateTimeString(),
            ];
            $alarmNotification->description = implode("\n", $descLines);
            $alarmNotification->save();

            Log::info('Alarm notification sent to user', [
                'user_id' => $user->id,
                'user_name' => $user->user_name,
                'task_id' => $task->id,
                'notification_number' => $currentNotificationNumber,
                'rest_max' => $restMax,
                'is_last' => $isLastNotification,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send alarm notification', [
                'user_id' => $user->id,
                'user_name' => $user->user_name,
                'task_id' => $task->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Check if a task is assigned to a user
     */
    private function isTaskAssignedToUser(Task $task, int $userId): bool
    {
        if (empty($task->users)) {
            return false;
        }

        $usersStr = $task->users;
        
        // Try to decode as JSON array
        $usersArray = json_decode($usersStr, true);
        if (is_array($usersArray)) {
            return in_array($userId, $usersArray, true);
        }

        // Fallback: try string matching for different formats
        $userIdStr = (string) $userId;
        return strpos($usersStr, '"' . $userIdStr . '"') !== false ||
               strpos($usersStr, '[' . $userIdStr . ']') !== false ||
               strpos($usersStr, '[' . $userIdStr . ',') !== false ||
               strpos($usersStr, ',' . $userIdStr . ',') !== false ||
               strpos($usersStr, ',' . $userIdStr . ']') !== false;
    }
}
