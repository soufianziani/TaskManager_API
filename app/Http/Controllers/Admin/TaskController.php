<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateTaskRequest;
use App\Models\Task;
use App\Models\Refuse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TaskController extends Controller
{
    /**
     * Create a new task.
     * Requires 'create task' permission. Super admin can always create tasks.
     */
    public function create(CreateTaskRequest $request): JsonResponse
    {
        $user = $request->user();

        // Check if user has permission to create tasks (super_admin bypasses)
        if (!$user->hasPermissionWithSuperAdminBypass('create task')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. You need "create task" permission to create tasks.',
                'data' => [
                    'user_type' => $user->type,
                    'required_permission' => 'create task',
                ],
            ], 403);
        }

        $task = Task::create([
            'name' => $request->name,
            'description' => $request->description,
            'type' => $request->type,
            'status' => $request->status ?? true,
            'url' => $request->url,
            'redirect' => $request->redirect ?? false,
            'department' => $request->department,
            'category_id' => $request->category_id,
            'type_id' => $request->type_id,
            'period_type' => $request->period_type,
            'period_start' => $request->period_start,
            'period_end' => $request->period_end,
            'period_days' => $request->period_days,
            'period_urgent' => $request->period_urgent,
            'type_justif' => $request->type_justif,
            'users' => $request->users,
            'step' => $request->step ?? 'pending',
            'file' => $request->file, // File ID from files table
            'justif_file' => null, // Always null when creating task
            'controller' => $request->controller, // Controller user ID or name
            'alarm' => $request->alarm, // Alarm times as JSON string
        ]);

        // Load file information
        $task->load('taskFile', 'justifFile');

        // Ensure justif_file raw value is included (for JSON array strings)
        // Get the raw value from attributes to preserve JSON array strings
        $taskData = $task->toArray();
        $taskData['justif_file'] = $task->attributes['justif_file'] ?? $task->justif_file; // Include raw value

        return response()->json([
            'success' => true,
            'message' => 'Task created successfully',
            'data' => $taskData,
        ], 201);
    }

    /**
     * Get task counts by type (pending tasks only).
     * - Super admin: sees counts for all tasks
     * - Regular users: sees counts only for their tasks
     */
    public function getTaskCountsByType(): JsonResponse
    {
        $user = request()->user();
        $userId = $user->id;

        // Build base query
        $baseQuery = Task::where('step', 'pending');

        // If user is not super_admin, filter by user ID in users field OR controller field
        if ($user->type !== 'super_admin') {
            $userName = $user->user_name;
            $userEmail = $user->email;
            
            $baseQuery->where(function ($q) use ($userId, $userName, $userEmail) {
                // Check if user is assigned (in users field)
                $q->where(function ($subQ) use ($userId) {
                    $subQ->where('users', 'like', '%"' . $userId . '"%')
                  ->orWhere('users', 'like', '%[' . $userId . ']%')
                  ->orWhere('users', 'like', '%[' . $userId . ',%')
                  ->orWhere('users', 'like', '%,' . $userId . ',%')
                  ->orWhere('users', 'like', '%,' . $userId . ']%')
                  ->orWhere('users', '=', '[' . $userId . ']')
                  ->orWhere('users', '=', '["' . $userId . '"]');
                })
                // OR check if user is controller
                ->orWhere(function ($subQ) use ($userId, $userName, $userEmail) {
                    $subQ->where('controller', $userId)
                      ->orWhere('controller', $userName)
                      ->orWhere('controller', $userEmail)
                      ->orWhere('controller', 'like', '%' . $userId . '%');
                });
            });
        }

        // Get pending task counts by type
        $counts = [
            'UI' => (clone $baseQuery)->where('type', 'UI')->count(),
            'NUI' => (clone $baseQuery)->where('type', 'NUI')->count(),
            'UNI' => (clone $baseQuery)->where('type', 'UNI')->count(),
            'NUNI' => (clone $baseQuery)->where('type', 'NUNI')->count(),
        ];

        return response()->json([
            'success' => true,
            'message' => 'Task counts retrieved successfully',
            'data' => $counts,
        ], 200);
    }

    /**
     * Get task counts by status (for Quick Stats).
     * - Super admin: sees counts for all tasks
     * - Regular users: sees counts only for their tasks
     */
    public function getTaskCountsByStatus(): JsonResponse
    {
        $user = request()->user();
        $userId = $user->id;

        // Build base query
        $baseQuery = Task::query();

        // If user is not super_admin, filter by user ID in users field OR controller field
        if ($user->type !== 'super_admin') {
            $userName = $user->user_name;
            $userEmail = $user->email;
            
            $baseQuery->where(function ($q) use ($userId, $userName, $userEmail) {
                // Check if user is assigned (in users field)
                $q->where(function ($subQ) use ($userId) {
                    $subQ->where('users', 'like', '%"' . $userId . '"%')
                  ->orWhere('users', 'like', '%[' . $userId . ']%')
                  ->orWhere('users', 'like', '%[' . $userId . ',%')
                  ->orWhere('users', 'like', '%,' . $userId . ',%')
                  ->orWhere('users', 'like', '%,' . $userId . ']%')
                  ->orWhere('users', '=', '[' . $userId . ']')
                  ->orWhere('users', '=', '["' . $userId . '"]');
                })
                // OR check if user is controller
                ->orWhere(function ($subQ) use ($userId, $userName, $userEmail) {
                    $subQ->where('controller', $userId)
                      ->orWhere('controller', $userName)
                      ->orWhere('controller', $userEmail)
                      ->orWhere('controller', 'like', '%' . $userId . '%');
                });
            });
        }

        // Get task counts by status
        $counts = [
            'total' => (clone $baseQuery)->count(),
            'completed' => (clone $baseQuery)->where('step', 'completed')->count(),
            'pending' => (clone $baseQuery)->where('step', 'pending')->count(),
            'in_progress' => (clone $baseQuery)->where('step', 'in_progress')->count(),
        ];

        return response()->json([
            'success' => true,
            'message' => 'Task counts retrieved successfully',
            'data' => $counts,
        ], 200);
    }

    /**
     * Get all tasks with optional filtering.
     * - Super admin: sees all tasks
     * - Regular users: see only tasks where their ID is in the users field
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        // All authenticated users can view tasks (but filtered based on type)
        $query = Task::query();

        // If user is not super_admin, filter by user ID in the users field OR controller field
        if ($user->type !== 'super_admin') {
            $userId = $user->id;
            $userName = $user->user_name;
            $userEmail = $user->email;
            
            // The users field is stored as JSON string like "[2]" or "[2,3]" or "[\"2\"]"
            // We need to check if the current user's ID is in that array
            // Also check if user is the controller
            // Try multiple formats to handle different JSON encodings
            $query->where(function ($q) use ($userId, $userName, $userEmail) {
                // Check if user is assigned (in users field)
                $q->where(function ($subQ) use ($userId) {
                // Handle JSON array format: ["2"] or ["2","3"]
                    $subQ->where('users', 'like', '%"' . $userId . '"%')
                  // Handle array format: [2] or [2,3]
                  ->orWhere('users', 'like', '%[' . $userId . ']%')
                  // Handle array with comma: [2, or ,2,
                  ->orWhere('users', 'like', '%[' . $userId . ',%')
                  ->orWhere('users', 'like', '%,' . $userId . ',%')
                  ->orWhere('users', 'like', '%,' . $userId . ']%')
                  // Handle single number in brackets
                  ->orWhere('users', '=', '[' . $userId . ']')
                  ->orWhere('users', '=', '["' . $userId . '"]');
                })
                // OR check if user is controller
                ->orWhere(function ($subQ) use ($userId, $userName, $userEmail) {
                    $subQ->where('controller', $userId)
                      ->orWhere('controller', $userName)
                      ->orWhere('controller', $userEmail)
                      ->orWhere('controller', 'like', '%' . $userId . '%');
                });
            });
        }

        // Filter by type
        if ($request->has('type') && $request->type !== null) {
            $query->where('type', $request->type);
        }

        // Filter by step
        if ($request->has('step') && $request->step !== null) {
            $query->where('step', $request->step);
        }

        // Filter by status
        if ($request->has('status') && $request->status !== null) {
            $query->where('status', $request->status);
        }

        // Filter by department
        if ($request->has('department') && $request->department !== null) {
            $query->where('department', 'like', '%' . $request->department . '%');
        }

        $tasks = $query->orderBy('created_at', 'desc')->get();

        // Load file information for each task
        $tasks->load('taskFile', 'justifFile');

        // Ensure justif_file raw value is included for each task (for JSON array strings)
        $tasksData = $tasks->map(function ($task) {
            $taskArray = $task->toArray();
            // Get the raw value from attributes to preserve JSON array strings
            $taskArray['justif_file'] = $task->attributes['justif_file'] ?? $task->justif_file; // Include raw value
            return $taskArray;
        });

        return response()->json([
            'success' => true,
            'message' => 'Tasks retrieved successfully',
            'data' => $tasksData,
        ], 200);
    }

    /**
     * Get a single task by ID.
     * - Super admin: can view any task
     * - Users with "Edit-tasks" permission: can view any task
     * - Regular users: can view tasks assigned to them
     */
    public function show(Request $request, $id): JsonResponse
    {
        $user = $request->user();
        $task = Task::findOrFail($id);

        // If user is not super_admin and doesn't have edit permission, check if task is assigned to them
        if ($user->type !== 'super_admin' && !$user->hasPermissionWithSuperAdminBypass('update task')) {
            $isAssignedToUser = $this->isTaskAssignedToUser($task, $user->id);
            
            // Check if user is controller
            $isTaskController = !empty($task->controller) && 
                ($task->controller == $user->id || 
                 $task->controller == $user->user_name ||
                 $task->controller == $user->email ||
                 strpos($task->controller, (string)$user->id) !== false);
            
            if (!$isAssignedToUser && !$isTaskController) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized. You do not have permission to view this task.',
                ], 403);
            }
        }

        // Load relationships
        $task->load('taskFile', 'justifFile');
        
        // Try to load category, type relationships (columns exist in table)
        try {
            $task->load('category', 'type');
        } catch (\Exception $e) {
            // Ignore if relationships fail
        }
        
        // Get task data as array - this includes relationships if loaded
        $taskData = $task->toArray();
        
        // Ensure justif_file raw value is included (for JSON array strings)
        $taskData['justif_file'] = $task->attributes['justif_file'] ?? $task->justif_file ?? null;
        
        // Ensure category_id, type_id are included in response (get from attributes first to get raw database value)
        $taskData['category_id'] = $task->getAttribute('category_id') ?? $task->attributes['category_id'] ?? null;
        $taskData['type_id'] = $task->getAttribute('type_id') ?? $task->attributes['type_id'] ?? null;
        
        // Ensure department is always a string (not a relationship object)
        // Get the raw attribute value to avoid relationship objects
        $taskData['department'] = $task->getAttribute('department') ?? $task->attributes['department'] ?? null;
        
        // If department is still an array (relationship), extract name
        if (is_array($taskData['department']) && isset($taskData['department']['name'])) {
            $taskData['department'] = $taskData['department']['name'];
        }

        return response()->json([
            'success' => true,
            'message' => 'Task retrieved successfully',
            'data' => $taskData,
        ], 200);
    }

    /**
     * Update an existing task.
     * - Super admin: can update any task
     * - Users with "Edit-tasks" permission: can update any task
     * - Regular users: can update step and justif_file for tasks assigned to them
     */
    public function update(Request $request, $id): JsonResponse
    {
        $user = $request->user();
        $task = Task::findOrFail($id);

        $isAssignedToUser = $this->isTaskAssignedToUser($task, $user->id);
        
        // Check if user is controller
        // Controller can be: 1) User is set as controller for this task, OR 2) User has "controller" permission
        $isTaskController = !empty($task->controller) && 
            ($task->controller == $user->id || 
             $task->controller == $user->user_name ||
             $task->controller == $user->email ||
             strpos($task->controller, (string)$user->id) !== false);
        
        // Check if user has controller permission (with super_admin bypass)
        $hasControllerPermission = $user->hasPermissionWithSuperAdminBypass('controller');
        $isController = $isTaskController || $hasControllerPermission;
        
        // Check if user can edit this task (with super_admin bypass)
        $canEdit = $user->hasPermissionWithSuperAdminBypass('update task');
        
        // If user can't edit, check if they can at least update step and justif_file (for completing tasks)
        if (!$canEdit) {
            // Check if user is controller
            if ($isController) {
                // If controller is also assigned to the task, allow them to move pending -> in_progress
                if ($isAssignedToUser && $task->step === 'pending') {
                    // Controller who is assigned can move pending -> in_progress
                    $isMovingToProgress = $request->filled('step') && $request->step === 'in_progress';
                    
                    if ($isMovingToProgress) {
                        // Allow updating step and justif_file
                        $allowedFields = ['step', 'justif_file'];
                        $requestFields = array_keys($request->all());
                        $unauthorizedFields = array_diff($requestFields, $allowedFields);
                        
                        if (!empty($unauthorizedFields)) {
                            return response()->json([
                                'success' => false,
                                'message' => 'Unauthorized. You can only update the task status (step) and justification files (justif_file).',
                            ], 403);
                        }
                    } else {
                        // Controller assigned user trying to do something other than move to progress
                        return response()->json([
                            'success' => false,
                            'message' => 'Unauthorized. You can only move the task from pending to in progress.',
                        ], 403);
                    }
                } elseif ($task->step === 'in_progress') {
                    // Controllers can complete in_progress tasks
                    $isCompletingTask = $request->filled('step') && $request->step === 'completed';
                    
                    if ($isCompletingTask) {
                        // Allow controllers to update step (justif_file is optional - can complete without files)
                        $allowedFields = ['step'];
                        // If justif_file is provided, allow it too
                        if ($request->has('justif_file')) {
                            $allowedFields[] = 'justif_file';
                        }
                        $requestFields = array_keys($request->all());
                        $unauthorizedFields = array_diff($requestFields, $allowedFields);
                        
                        if (!empty($unauthorizedFields)) {
                            return response()->json([
                                'success' => false,
                                'message' => 'Unauthorized. Controllers can only complete tasks by updating the step. You cannot edit other task details.',
                            ], 403);
                        }
                    } else {
                        // Controller trying to do something other than complete - not allowed
                        return response()->json([
                            'success' => false,
                            'message' => 'Unauthorized. Controllers can only complete tasks that are in progress. You cannot edit other task details.',
                        ], 403);
                    }
                } else {
                    // Controller trying to edit task in other states - not allowed
                    return response()->json([
                        'success' => false,
                        'message' => 'Unauthorized. Controllers can only complete tasks that are in progress. You cannot edit other task details.',
                    ], 403);
                }
            } elseif ($isAssignedToUser && !$isController) {
                // Regular assigned users (not controllers)
                // Check if task has a controller
                $hasController = !empty($task->controller);
                
                if ($task->step === 'in_progress') {
                    // If task has no controller, allow user to complete it
                    if (!$hasController) {
                        // Allow updating step to completed and justif_file
                        $allowedFields = ['step', 'justif_file'];
                        $requestFields = array_keys($request->all());
                        $unauthorizedFields = array_diff($requestFields, $allowedFields);
                        
                        if (!empty($unauthorizedFields)) {
                            return response()->json([
                                'success' => false,
                                'message' => 'Unauthorized. You can only update the task status (step) and justification files (justif_file).',
                            ], 403);
                        }
                    } else {
                        // Task has controller - assigned users cannot edit in_progress tasks
                        return response()->json([
                            'success' => false,
                            'message' => 'Unauthorized. When a task is in progress, assigned users can only view the task. Only controllers can complete it.',
                        ], 403);
                    }
                } else {
                    // Task is pending - allow updating step and justif_file
                    // Regular users can move pending -> in_progress
                    // If no controller, they can also move pending -> completed directly
                    $allowedFields = ['step', 'justif_file'];
                    $requestFields = array_keys($request->all());
                    $unauthorizedFields = array_diff($requestFields, $allowedFields);
                    
                    if (!empty($unauthorizedFields)) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Unauthorized. You can only update the task status (step) and justification files (justif_file) for assigned tasks.',
                        ], 403);
                    }
                }
            } else {
                // User is neither assigned nor controller
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized. You do not have permission to edit this task.',
                ], 403);
            }
        }

        // Update task fields
        if ($request->filled('name')) {
            $task->name = $request->name;
        }
        if ($request->has('description')) {
            $task->description = $request->description;
        }
        if ($request->filled('type')) {
            $task->type = $request->type;
        }
        if ($request->has('status')) {
            $task->status = $request->status;
        }
        if ($request->has('url')) {
            $task->url = $request->url;
        }
        if ($request->has('redirect')) {
            $task->redirect = $request->redirect;
        }
        if ($request->has('department')) {
            $task->department = $request->department;
        }
        if ($request->has('category_id')) {
            // Allow null to clear category, or set new category_id
            $task->category_id = $request->category_id;
        }
        if ($request->has('type_id')) {
            // Allow null to clear type, or set new type_id
            $task->type_id = $request->type_id;
        }
        if ($request->has('period_type')) {
            $task->period_type = $request->period_type;
        }
        if ($request->has('period_start')) {
            $task->period_start = $request->period_start;
        }
        if ($request->has('period_end')) {
            $task->period_end = $request->period_end;
        }
        if ($request->has('period_days')) {
            $task->period_days = $request->period_days;
        }
        if ($request->has('period_urgent')) {
            $task->period_urgent = $request->period_urgent;
        }
        if ($request->has('type_justif')) {
            $task->type_justif = $request->type_justif;
        }
        if ($request->has('users')) {
            $task->users = $request->users;
        }
        // Check if user is trying to move task from pending to in_progress
        // If task has alarm, verify that alarm time has passed
        if ($request->filled('step') && $request->step === 'in_progress' && $task->step === 'pending') {
            // Check if task has alarm (for periodic tasks)
            if (!empty($task->alarm)) {
                $alarmCheck = $this->checkAlarmTime($task);
                if (!$alarmCheck['allowed']) {
                    return response()->json([
                        'success' => false,
                        'message' => $alarmCheck['message'],
                    ], 400);
                }
            }
        }
        
        if ($request->filled('step')) {
            $task->step = $request->step;
        }
        if ($request->has('file')) {
            $task->file = $request->file;
        }
        if ($request->filled('justif_file')) {
            $task->justif_file = $request->justif_file;
            
            // If task has no controller and user is assigned, move directly to completed
            // This happens when user justifies a pending or in_progress task without controller
            if (empty($task->controller) && $isAssignedToUser) {
                if ($task->step === 'pending' || $task->step === 'in_progress') {
                    // Move directly from pending/in_progress to completed when user justifies
                    $task->step = 'completed';
                }
            }
        }
        
        if ($request->has('controller')) {
            // Allow setting controller to null or a value
            if ($request->controller === null || $request->controller === '') {
                $task->controller = null;
            } else {
                $task->controller = $request->controller;
            }
        }
        if ($request->has('alarm')) {
            $task->alarm = $request->alarm;
        }

        $task->save();

        // Reload the task to get the updated data with relationships
        $task->refresh();
        $task->load('taskFile', 'justifFile');

        // Ensure justif_file raw value is included (for JSON array strings)
        // Get the raw value from attributes to preserve JSON array strings
        $taskData = $task->toArray();
        $taskData['justif_file'] = $task->attributes['justif_file'] ?? $task->justif_file; // Include raw value

        return response()->json([
            'success' => true,
            'message' => 'Task updated successfully',
            'data' => $taskData,
        ], 200);
    }

    /**
     * Check if alarm time has passed for a task.
     * Returns array with 'allowed' (bool) and 'message' (string).
     */
    private function checkAlarmTime(Task $task): array
    {
        try {
            // Parse alarm JSON
            $alarmData = json_decode($task->alarm, true);
            if (!is_array($alarmData) || empty($alarmData)) {
                // No valid alarm data, allow the step change
                return ['allowed' => true, 'message' => ''];
            }

            $now = \Carbon\Carbon::now();
            
            // Check if task is periodic
            $isPeriodic = !empty($task->period_type) && strpos($task->period_type, 'periodic') !== false;
            
            if (!$isPeriodic) {
                // Not a periodic task, allow step change
                return ['allowed' => true, 'message' => ''];
            }

            // Check if we're within the period range
            if (!empty($task->period_start) && !empty($task->period_end)) {
                $periodStart = \Carbon\Carbon::parse($task->period_start);
                $periodEnd = \Carbon\Carbon::parse($task->period_end);
                
                // Check if current time is before period start
                if ($now->lt($periodStart)) {
                    return [
                        'allowed' => false,
                        'message' => 'Task period has not started yet. Please wait until ' . $periodStart->format('Y-m-d H:i:s'),
                    ];
                }
                
                // Check if current time is after period end
                if ($now->gt($periodEnd)) {
                    // Period has ended, allow step change
                    return ['allowed' => true, 'message' => ''];
                }
            }

            // Extract frequency from period_type (format: "periodic (daily)" or "periodic (weekly)" or "periodic (monthly)")
            $frequency = null;
            if (preg_match('/periodic\s*\((\w+)\)/', $task->period_type, $matches)) {
                $frequency = $matches[1];
            }

            // Get alarm time based on frequency
            $alarmTime = null;
            
            if ($frequency === 'daily') {
                // For daily, check if 'daily' key exists, otherwise check 'all'
                if (isset($alarmData['daily'])) {
                    $alarmTime = $alarmData['daily'];
                } elseif (isset($alarmData['all'])) {
                    $alarmTime = $alarmData['all'];
                }
            } elseif ($frequency === 'weekly') {
                // Get current day of week (lowercase)
                $currentDay = strtolower($now->format('l')); // Monday, Tuesday, etc.
                
                // Check if current day is in selected period_days
                $periodDays = [];
                if (!empty($task->period_days)) {
                    $periodDaysArray = json_decode($task->period_days, true);
                    if (is_array($periodDaysArray)) {
                        $periodDays = array_map('strtolower', $periodDaysArray);
                    }
                }
                
                // Only check alarm if current day is in selected days (or if no days selected, allow)
                if (empty($periodDays) || in_array($currentDay, $periodDays)) {
                    // Check if current day has specific alarm time
                    if (isset($alarmData[$currentDay])) {
                        $alarmTime = $alarmData[$currentDay];
                    } elseif (isset($alarmData['all'])) {
                        $alarmTime = $alarmData['all'];
                    }
                }
            } elseif ($frequency === 'monthly') {
                // Get current day of month (1-31)
                $currentDayOfMonth = $now->day;
                
                // Check if current day is in selected period_days
                $periodDays = [];
                if (!empty($task->period_days)) {
                    $periodDaysArray = json_decode($task->period_days, true);
                    if (is_array($periodDaysArray)) {
                        $periodDays = array_map('strval', $periodDaysArray);
                    }
                }
                
                // Only check alarm if current day is in selected days (or if no days selected, allow)
                if (empty($periodDays) || in_array((string)$currentDayOfMonth, $periodDays)) {
                    // Check if current day has specific alarm time
                    if (isset($alarmData[(string)$currentDayOfMonth])) {
                        $alarmTime = $alarmData[(string)$currentDayOfMonth];
                    } elseif (isset($alarmData['all'])) {
                        $alarmTime = $alarmData['all'];
                    }
                }
            }

            // If no alarm time found, allow step change
            if (empty($alarmTime)) {
                return ['allowed' => true, 'message' => ''];
            }

            // Parse alarm time (format: "HH:mm")
            $timeParts = explode(':', $alarmTime);
            if (count($timeParts) !== 2) {
                // Invalid time format, allow step change
                return ['allowed' => true, 'message' => ''];
            }

            $alarmHour = (int)$timeParts[0];
            $alarmMinute = (int)$timeParts[1];

            // Create alarm datetime for today
            $alarmDateTime = \Carbon\Carbon::create(
                $now->year,
                $now->month,
                $now->day,
                $alarmHour,
                $alarmMinute,
                0
            );

            // Check if alarm time has passed
            if ($now->lt($alarmDateTime)) {
                return [
                    'allowed' => false,
                    'message' => 'Alarm time has not been reached yet. Please wait until ' . $alarmDateTime->format('Y-m-d H:i:s') . ' to start this task.',
                ];
            }

            // Alarm time has passed, allow step change
            return ['allowed' => true, 'message' => ''];
            
        } catch (\Exception $e) {
            // If there's any error parsing alarm, allow step change (fail open)
            return ['allowed' => true, 'message' => ''];
        }
    }

    /**
     * Check if a task is assigned to a user.
     * The users field is stored as JSON string like "[2]" or "[2,3]" or "[\"2\"]"
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

    /**
     * Refuse a task (move from in_progress to pending).
     * Only controllers can refuse tasks that are in_progress.
     */
    public function refuse(Request $request, $id): JsonResponse
    {
        $user = $request->user();
        $task = Task::findOrFail($id);

        // Check if user is controller (task controller or has controller permission)
        $isTaskController = !empty($task->controller) && 
            ($task->controller == $user->id || 
             $task->controller == $user->user_name ||
             $task->controller == $user->email ||
             strpos($task->controller, (string)$user->id) !== false);
        
        $hasControllerPermission = $user->hasPermissionWithSuperAdminBypass('controller');
        $isController = $isTaskController || $hasControllerPermission;

        // Only controllers can refuse tasks
        if (!$isController) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Only controllers can refuse tasks.',
            ], 403);
        }

        // Only in_progress tasks can be refused
        if ($task->step !== 'in_progress') {
            return response()->json([
                'success' => false,
                'message' => 'Only tasks in progress can be refused.',
            ], 400);
        }

        // Check if period_end is expired (less than or equal to current time)
        $isPeriodEndExpired = false;
        if (!empty($task->period_end)) {
            try {
                $periodEndDate = \Carbon\Carbon::parse($task->period_end);
                $isPeriodEndExpired = $periodEndDate->lte(\Carbon\Carbon::now());
            } catch (\Exception $e) {
                // If parsing fails, treat as expired
                $isPeriodEndExpired = true;
            }
        } else {
            // No period_end set, treat as expired
            $isPeriodEndExpired = true;
        }

        // Validate request
        $validationRules = [
            'description' => 'required|string|min:10',
        ];
        
        // If period_end is expired, it's mandatory
        if ($isPeriodEndExpired) {
            $validationRules['period_end'] = 'required|date|after:' . \Carbon\Carbon::now()->format('Y-m-d H:i:s');
        } else {
            $validationRules['period_end'] = 'nullable|date';
            // If provided, it should be in the future
            if ($request->has('period_end') && !empty($request->period_end)) {
                $validationRules['period_end'] .= '|after:' . \Carbon\Carbon::now()->format('Y-m-d H:i:s');
            }
        }
        
        $request->validate($validationRules);

        try {
            // Create refusal record
            // Task ID is stored as varchar(255) in the database
            $refuse = Refuse::create([
                'description' => $request->description,
                'task' => (string)$task->id, // Store as string (varchar)
                'created_by' => $user->id,
            ]);

            // Move task back to pending
            $task->step = 'pending';
            
            // Update period_end if provided
            if ($request->has('period_end') && !empty($request->period_end)) {
                $task->period_end = $request->period_end;
            }
            
            $task->save();

            // Reload the task to get the updated data
            $task->refresh();
            $task->load('taskFile', 'justifFile');

            // Ensure justif_file raw value is included
            $taskData = $task->toArray();
            $taskData['justif_file'] = $task->attributes['justif_file'] ?? $task->justif_file;

            return response()->json([
                'success' => true,
                'message' => 'Task refused and moved back to pending successfully',
                'data' => [
                    'task' => $taskData,
                    'refuse' => $refuse,
                ],
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error refusing task: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get refuse history for a task.
     * - Super admin and admin can view all refuse history
     * - Controller of the task can view refuse history
     * - Assigned users (task users) can view refuse history for their tasks (to see why task was refused)
     */
    public function getRefuseHistory(Request $request, $id): JsonResponse
    {
        $user = $request->user();
        $task = Task::findOrFail($id);

        // Check if user is controller (task controller or has controller permission)
        $isTaskController = !empty($task->controller) && 
            ($task->controller == $user->id || 
             $task->controller == $user->user_name ||
             $task->controller == $user->email ||
             strpos($task->controller, (string)$user->id) !== false);
        
        $hasControllerPermission = $user->hasPermissionWithSuperAdminBypass('controller');
        $isController = $isTaskController || $hasControllerPermission;
        
        // Check if task is assigned to user
        $isAssignedToUser = $this->isTaskAssignedToUser($task, $user->id);
        
        // Check if user has actors permission (with super_admin bypass)
        $hasActorsPermission = $user->hasPermissionWithSuperAdminBypass('actors');

        // Allow: super_admin (automatic), users with actors permission, controller, or assigned users (task users)
        if (!$hasActorsPermission && !$isController && !$isAssignedToUser) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Only super admin, users with actors permission, task controller, or assigned users can view refuse history.',
            ], 403);
        }

        try {
            // Get all refuses for this task, ordered by created_at descending
            // Task ID is stored as varchar(255) in the refuse table, so we compare as string
            $refuses = Refuse::where('task', (string)$task->id)
                ->with('creator:id,user_name,email,phone')
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Refuse history retrieved successfully',
                'data' => $refuses,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving refuse history: ' . $e->getMessage(),
            ], 500);
        }
    }
}
