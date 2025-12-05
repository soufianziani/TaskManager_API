<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateTaskRequest;
use App\Models\Task;
use App\Models\Refuse;
use App\Models\User;
use App\Models\Delay;
use App\Models\TaskNotification;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;

class TaskController extends Controller
{
    private $messaging = null;
    private $initializationError = null;
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
            'task_name' => $request->task_name,
            'period_type' => $request->period_type,
            'period_start' => $request->period_start,
            'period_end' => $request->period_end,
            'time_cloture' => $request->time_cloture,
            'time_out' => $request->time_out,
            'period_days' => $request->period_days,
            'period_urgent' => $request->period_urgent,
            'type_justif' => $request->type_justif,
            'users' => $request->users,
            'step' => $request->step ?? 'pending',
            'file' => $request->file, // File ID from files table
            'justif_file' => null, // Always null when creating task
            'controller' => $request->controller, // Controller user ID or name
            'alarm' => $request->alarm, // Alarm times as JSON string
            'rest_time' => $request->rest_time, // Rest time in HH:mm:ss format
            'rest_max' => $request->rest_max,
            'created_by' => (string)$user->id, // Store creator user ID
        ]);

        // Load file information
        $task->load('taskFile', 'justifFile');

        // Ensure justif_file raw value is included (for JSON array strings)
        // Get the raw value from attributes to preserve JSON array strings
        $taskData = $task->toArray();
        $taskData['justif_file'] = $task->attributes['justif_file'] ?? $task->justif_file; // Include raw value

        // Send notifications to assigned users
        $this->sendNotificationToAssignedUsers(
            $task,
            'New Task Assigned',
            "You have been assigned to a new task: {$task->name}"
        );

        return response()->json([
            'success' => true,
            'message' => 'Task created successfully',
            'data' => $taskData,
        ], 201);
    }

    /**
     * Get task counts by type (pending tasks only).
     * - Super admin: sees counts for all tasks
     * - Users with "show all pending" permission: sees counts for all pending tasks
     * - Regular users: sees counts only for their tasks
     */
    public function getTaskCountsByType(): JsonResponse
    {
        $user = request()->user();
        $userId = $user->id;

        // Check if user has permission to show all pending tasks
        $canShowAllPending = $user->hasPermissionWithSuperAdminBypass('show all pending');

        // Build base query
        $baseQuery = Task::where('step', 'pending');

        // If user is not super_admin and doesn't have permission to show all pending, filter by user assignment
        if ($user->type !== 'super_admin' && !$canShowAllPending) {
            $userId = $user->id;
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
                })
                // OR check if user is the creator (created_by)
                ->orWhere('created_by', (string)$userId);
            });
        }

        // Get all pending tasks first
        $tasks = $baseQuery->get();

        // Filter tasks that have passed timeout
        $now = Carbon::now();
        $filteredTasks = $tasks->filter(function ($task) use ($now) {
            // Task must have both time_cloture and time_out set
            if (empty($task->time_cloture) || empty($task->time_out)) {
                return false; // Exclude tasks without timeout configuration
            }
            
            // Calculate timeout datetime (time_cloture - time_out)
            $timeoutDateTime = $task->calculateTimeoutDateTime();
            
            // Only include tasks where timeout has passed (current time >= timeout datetime)
            return $timeoutDateTime && $now->gte($timeoutDateTime);
        });

        // Count by type
        $counts = [
            'UI' => $filteredTasks->where('type', 'UI')->count(),
            'NUI' => $filteredTasks->where('type', 'NUI')->count(),
            'UNI' => $filteredTasks->where('type', 'UNI')->count(),
            'NUNI' => $filteredTasks->where('type', 'NUNI')->count(),
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
     * - Users with "show all pending/processed/completed" permission: see all tasks with that status
     * - Regular users: sees counts only for their tasks
     */
    public function getTaskCountsByStatus(): JsonResponse
    {
        $user = request()->user();
        $userId = $user->id;

        // Check permissions for showing all tasks by status
        $canShowAllPending = $user->hasPermissionWithSuperAdminBypass('show all pending');
        $canShowAllProcessed = $user->hasPermissionWithSuperAdminBypass('show all processed');
        $canShowAllCompleted = $user->hasPermissionWithSuperAdminBypass('show all completed');

        // Build base query for pending tasks
        $pendingQuery = Task::where('step', 'pending');
        if ($user->type !== 'super_admin' && !$canShowAllPending) {
            $userId = $user->id;
            $userName = $user->user_name;
            $userEmail = $user->email;
            $pendingQuery->where(function ($q) use ($userId, $userName, $userEmail) {
                $q->where(function ($subQ) use ($userId) {
                    $subQ->where('users', 'like', '%"' . $userId . '"%')
                      ->orWhere('users', 'like', '%[' . $userId . ']%')
                      ->orWhere('users', 'like', '%[' . $userId . ',%')
                      ->orWhere('users', 'like', '%,' . $userId . ',%')
                      ->orWhere('users', 'like', '%,' . $userId . ']%')
                      ->orWhere('users', '=', '[' . $userId . ']')
                      ->orWhere('users', '=', '["' . $userId . '"]');
                })
                ->orWhere(function ($subQ) use ($userId, $userName, $userEmail) {
                    $subQ->where('controller', $userId)
                      ->orWhere('controller', $userName)
                      ->orWhere('controller', $userEmail)
                      ->orWhere('controller', 'like', '%' . $userId . '%');
                })
                // OR check if user is the creator (created_by)
                ->orWhere('created_by', (string)$userId);
            });
        }

        // Build base query for processed tasks
        $processedQuery = Task::where('step', 'in_progress');
        if ($user->type !== 'super_admin' && !$canShowAllProcessed) {
            $userId = $user->id;
            $userName = $user->user_name;
            $userEmail = $user->email;
            $processedQuery->where(function ($q) use ($userId, $userName, $userEmail) {
                $q->where(function ($subQ) use ($userId) {
                    $subQ->where('users', 'like', '%"' . $userId . '"%')
                      ->orWhere('users', 'like', '%[' . $userId . ']%')
                      ->orWhere('users', 'like', '%[' . $userId . ',%')
                      ->orWhere('users', 'like', '%,' . $userId . ',%')
                      ->orWhere('users', 'like', '%,' . $userId . ']%')
                      ->orWhere('users', '=', '[' . $userId . ']')
                      ->orWhere('users', '=', '["' . $userId . '"]');
                })
                ->orWhere(function ($subQ) use ($userId, $userName, $userEmail) {
                    $subQ->where('controller', $userId)
                      ->orWhere('controller', $userName)
                      ->orWhere('controller', $userEmail)
                      ->orWhere('controller', 'like', '%' . $userId . '%');
                })
                // OR check if user is the creator (created_by)
                ->orWhere('created_by', (string)$userId);
            });
        }

        // Build base query for completed tasks
        $completedQuery = Task::where('step', 'completed');
        if ($user->type !== 'super_admin' && !$canShowAllCompleted) {
            $userId = $user->id;
            $userName = $user->user_name;
            $userEmail = $user->email;
            $completedQuery->where(function ($q) use ($userId, $userName, $userEmail) {
                $q->where(function ($subQ) use ($userId) {
                    $subQ->where('users', 'like', '%"' . $userId . '"%')
                      ->orWhere('users', 'like', '%[' . $userId . ']%')
                      ->orWhere('users', 'like', '%[' . $userId . ',%')
                      ->orWhere('users', 'like', '%,' . $userId . ',%')
                      ->orWhere('users', 'like', '%,' . $userId . ']%')
                      ->orWhere('users', '=', '[' . $userId . ']')
                      ->orWhere('users', '=', '["' . $userId . '"]');
                })
                ->orWhere(function ($subQ) use ($userId, $userName, $userEmail) {
                    $subQ->where('controller', $userId)
                      ->orWhere('controller', $userName)
                      ->orWhere('controller', $userEmail)
                      ->orWhere('controller', 'like', '%' . $userId . '%');
                })
                // OR check if user is the creator (created_by)
                ->orWhere('created_by', (string)$userId);
            });
        }

        // Build base query for total (all tasks user can see)
        $baseQuery = Task::query();
        if ($user->type !== 'super_admin') {
            $userId = $user->id;
            $userName = $user->user_name;
            $userEmail = $user->email;
            $baseQuery->where(function ($q) use ($userId, $userName, $userEmail) {
                $q->where(function ($subQ) use ($userId) {
                    $subQ->where('users', 'like', '%"' . $userId . '"%')
                      ->orWhere('users', 'like', '%[' . $userId . ']%')
                      ->orWhere('users', 'like', '%[' . $userId . ',%')
                      ->orWhere('users', 'like', '%,' . $userId . ',%')
                      ->orWhere('users', 'like', '%,' . $userId . ']%')
                      ->orWhere('users', '=', '[' . $userId . ']')
                      ->orWhere('users', '=', '["' . $userId . '"]');
                })
                ->orWhere(function ($subQ) use ($userId, $userName, $userEmail) {
                    $subQ->where('controller', $userId)
                      ->orWhere('controller', $userName)
                      ->orWhere('controller', $userEmail)
                      ->orWhere('controller', 'like', '%' . $userId . '%');
                })
                // OR check if user is the creator (created_by)
                ->orWhere('created_by', (string)$userId);
            });
        }

        // Get task counts by status
        $counts = [
            'total' => (clone $baseQuery)->count(),
            'completed' => $completedQuery->count(),
            'pending' => $pendingQuery->count(),
            'in_progress' => $processedQuery->count(),
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
     * - Users with "show all pending/processed/completed" permission: see all tasks with that status
     * - Regular users: see only tasks where their ID is in the users field
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        // All authenticated users can view tasks (but filtered based on type)
        $query = Task::query();

        // Check if user has permissions to show all tasks for specific status
        $stepFilter = $request->has('step') && $request->step !== null ? $request->step : null;
        $canShowAllPending = $user->hasPermissionWithSuperAdminBypass('show all pending');
        $canShowAllProcessed = $user->hasPermissionWithSuperAdminBypass('show all processed');
        $canShowAllCompleted = $user->hasPermissionWithSuperAdminBypass('show all completed');
        
        // Determine if user can see all tasks based on permission and filter
        $canShowAllTasks = false;
        if ($stepFilter === 'pending' && $canShowAllPending) {
            $canShowAllTasks = true;
        } elseif ($stepFilter === 'in_progress' && $canShowAllProcessed) {
            $canShowAllTasks = true;
        } elseif ($stepFilter === 'completed' && $canShowAllCompleted) {
            $canShowAllTasks = true;
        }

        // If user is not super_admin and doesn't have permission to show all for this status, filter by user assignment
        if ($user->type !== 'super_admin' && !$canShowAllTasks) {
            $userId = $user->id;
            $userName = $user->user_name;
            $userEmail = $user->email;
            
            // The users field is stored as JSON string like "[2]" or "[2,3]" or "[\"2\"]"
            // We need to check if the current user's ID is in that array
            // Also check if user is the controller
            // Also check if user is the creator (created_by)
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
                })
                // OR check if user is the creator (created_by)
                ->orWhere('created_by', (string)$userId);
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

        // Filter tasks that have passed timeout ONLY when timeout_passed parameter is true
        // This is used by home page buttons, but NOT by the pending page
        if ($stepFilter === 'pending' && $request->has('timeout_passed') && $request->timeout_passed == '1') {
            $now = Carbon::now();
            $tasks = $tasks->filter(function ($task) use ($now) {
                // Task must have both time_cloture and time_out set
                if (empty($task->time_cloture) || empty($task->time_out)) {
                    return false; // Exclude tasks without timeout configuration
                }
                
                // Calculate timeout datetime (time_cloture - time_out)
                $timeoutDateTime = $task->calculateTimeoutDateTime();
                
                // Only include tasks where timeout has passed (current time >= timeout datetime)
                return $timeoutDateTime && $now->gte($timeoutDateTime);
            })->values(); // Reset keys after filtering
        }

        // Filter tasks that are in timeout (before closure time) when in_timeout parameter is true
        // This is used when filtering by type (UI, NUI, UNI, NUNI) to show only tasks in timeout
        if ($request->has('in_timeout') && $request->in_timeout == '1') {
            $now = Carbon::now();
            $tasks = $tasks->filter(function ($task) use ($now) {
                // Task must have time_cloture set
                if (empty($task->time_cloture)) {
                    return false; // Exclude tasks without closure time
                }
                
                // Calculate end datetime from time_cloture
                $endDateTime = $task->calculateEndDateTime();
                
                if (!$endDateTime) {
                    return false;
                }
                
                // Only include tasks where current time is before closure time (in timeout)
                return $now->lt($endDateTime);
            })->values(); // Reset keys after filtering
        }

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
            
            // Check if user is the creator (creators can view their tasks even if not assigned)
            $isCreator = !empty($task->created_by) && $task->created_by == (string)$user->id;
            
            if (!$isAssignedToUser && !$isTaskController && !$isCreator) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized. You do not have permission to view this task.',
                ], 403);
            }
        }

        // Load relationships
        $task->load('taskFile', 'justifFile');
        
        // Try to load category, task name relationships (columns exist in table)
        try {
            $task->load('category', 'taskNameRelation');
        } catch (\Exception $e) {
            // Ignore if relationships fail
        }
        
        // Get task data as array - this includes relationships if loaded
        $taskData = $task->toArray();
        
        // Ensure justif_file raw value is included (for JSON array strings)
        $taskData['justif_file'] = $task->attributes['justif_file'] ?? $task->justif_file ?? null;
        
        // Ensure category_id, task_name are included in response (get from attributes first to get raw database value)
        $taskData['category_id'] = $task->getAttribute('category_id') ?? $task->attributes['category_id'] ?? null;
        $taskData['task_name'] = $task->getAttribute('task_name') ?? $task->attributes['task_name'] ?? null;
        
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

        // Check if user is the creator of this task
        $isCreator = !empty($task->created_by) && $task->created_by == (string)$user->id;
        
        // If user is the creator, they can only edit if they are super admin or have "update task" permission
        if ($isCreator && $user->type !== 'super_admin' && !$user->hasPermissionWithSuperAdminBypass('update task')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. You created this task but do not have permission to edit it. Only super admins or users with "update task" permission can edit tasks.',
            ], 403);
        }

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
        
        // Check if controller is trying to complete a task in_progress (regardless of edit permission)
        // This allows controllers (super admin, admin, or regular user) to complete tasks when in_progress
        if ($isController && $task->step === 'in_progress') {
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
                // Controller can complete - continue to update logic below
            } elseif (!$canEdit) {
                // Controller trying to do something other than complete and doesn't have edit permission
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized. Controllers can only complete tasks that are in progress. You cannot edit other task details.',
                ], 403);
            }
            // If controller has edit permission and is not completing, allow normal edit flow below
        }
        
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
                    // This case is already handled above, but keep for backward compatibility
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
        if ($request->has('task_name')) {
            // Allow null to clear task name, or set new task_name
            $task->task_name = $request->task_name;
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
        if ($request->has('time_cloture')) {
            $task->time_cloture = $request->time_cloture;
        }
        if ($request->has('time_out')) {
            $task->time_out = $request->time_out;
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
        
        // Track if step changed for notification
        $oldStep = $task->step;
        $stepChanged = false;
        
        if ($request->filled('step')) {
            $newStep = $request->step;
            if ($oldStep !== $newStep) {
                $task->step = $newStep;
                $stepChanged = true;
            }
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
                    if (!$stepChanged) {
                        $oldStep = $task->step;
                        $stepChanged = true;
                    }
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
        if ($request->has('rest_time')) {
            $task->rest_time = $request->rest_time;
        }
        if ($request->has('rest_max')) {
            $task->rest_max = $request->rest_max;
        }

        $task->save();

        // Reload the task to get the updated data with relationships
        $task->refresh();
        $task->load('taskFile', 'justifFile');

        // Send notification if step changed
        if ($stepChanged) {
            $stepLabels = [
                'pending' => 'Pending',
                'in_progress' => 'In Progress',
                'completed' => 'Completed'
            ];
            
            $oldStepLabel = $stepLabels[$oldStep] ?? $oldStep;
            $newStepLabel = $stepLabels[$task->step] ?? $task->step;
            
            // If task moved from pending to in_progress and has controller, notify controller
            if ($oldStep === 'pending' && $task->step === 'in_progress' && !empty($task->controller)) {
                $this->sendNotificationToController(
                    $task,
                    'Task Moved to Next Step',
                    "Task '{$task->name}' has been moved to In Progress and requires your review"
                );
            } else {
                // For other step changes, notify assigned users
                $this->sendNotificationToAssignedUsers(
                    $task,
                    'Task Status Updated',
                    "Task '{$task->name}' status changed from {$oldStepLabel} to {$newStepLabel}"
                );
            }
        }

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

            // NEW FORMAT: alarm offset as days / hours / minutes
            // {
            //   "days": 1,
            //   "hours": 2,
            //   "minutes": 30
            // }
            if (isset($alarmData['days']) || isset($alarmData['hours']) || isset($alarmData['minutes'])) {
                $days = (int)($alarmData['days'] ?? 0);
                $hours = (int)($alarmData['hours'] ?? 0);
                $minutes = (int)($alarmData['minutes'] ?? 0);
                $seconds = (int)($alarmData['seconds'] ?? 0);

                // Base time: use period_start if available, otherwise created_at, otherwise now
                if (!empty($task->period_start)) {
                    try {
                        $base = \Carbon\Carbon::parse($task->period_start);
                    } catch (\Exception $e) {
                        $base = $task->created_at ?? $now;
                    }
                } else {
                    $base = $task->created_at ?? $now;
                }

                $alarmDateTime = $base->copy()
                    ->addDays($days)
                    ->addHours($hours)
                    ->addMinutes($minutes)
                    ->addSeconds($seconds);

                if ($now->lt($alarmDateTime)) {
                    return [
                        'allowed' => false,
                        'message' => 'Alarm time has not been reached yet. Please wait until ' . $alarmDateTime->format('Y-m-d H:i:s'),
                    ];
                }

                // Offset-based alarm has passed, allow step change
                return ['allowed' => true, 'message' => ''];
            }
            
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

            // Extract frequency from period_type (format: "periodic (daily)" or "periodic (weekly)" or "periodic (monthly)" etc.)
            $frequency = null;
            if (preg_match('/periodic\s*\((daily|weekly|monthly|trimesterly|semesterly|yearly)\)/', $task->period_type, $matches)) {
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
            } elseif (in_array($frequency, ['trimesterly', 'semesterly', 'yearly'])) {
                // For yearly frequency types, check if current date matches any date in period_days
                $periodDays = [];
                if (!empty($task->period_days)) {
                    $periodDaysArray = json_decode($task->period_days, true);
                    if (is_array($periodDaysArray)) {
                        $periodDays = $periodDaysArray;
                    }
                }
                
                // Check if current date (without time) matches any date in period_days
                $currentDateStr = $now->format('Y-m-d');
                $dateMatched = false;
                
                foreach ($periodDays as $dateStr) {
                    try {
                        // Parse the date string and compare dates (ignore time)
                        $taskDate = \Carbon\Carbon::parse($dateStr);
                        if ($taskDate->format('Y-m-d') === $currentDateStr) {
                            $dateMatched = true;
                            break;
                        }
                    } catch (\Exception $e) {
                        // Ignore parse errors
                        continue;
                    }
                }
                
                // Only check alarm if current date matches one of the selected dates
                if ($dateMatched) {
                    // For yearly types, check for date-specific time first, then fallback to 'all'
                    // Alarm data may use YYYY-MM-DD format as keys when different times per day
                    if (isset($alarmData[$currentDateStr])) {
                        $alarmTime = $alarmData[$currentDateStr];
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
            if (!empty($privateKey)) {
                $privateKey = str_replace(['\\n', '\n'], "\n", $privateKey);
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

            // Create temporary JSON file for Firebase credentials
            $tempFile = tempnam(sys_get_temp_dir(), 'firebase_credentials_');
            file_put_contents($tempFile, json_encode($credentials, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
            
            try {
                $factory = (new Factory)->withServiceAccount($tempFile);
                $this->messaging = $factory->createMessaging();
                
                Log::info('Firebase messaging initialized successfully in TaskController');
                
                return $this->messaging;
            } finally {
                // Clean up temporary file
                if (file_exists($tempFile)) {
                    @unlink($tempFile);
                }
            }
        } catch (\Exception $e) {
            $this->initializationError = $e->getMessage();
            Log::error('Firebase initialization error in TaskController: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Send notification to controller user of a task
     */
    private function sendNotificationToController(Task $task, string $title, string $body): void
    {
        try {
            if (empty($task->controller)) {
                Log::info('Task has no controller, skipping notification', ['task_id' => $task->id]);
                return;
            }

            $controller = $task->controller;
            $controllerUser = null;

            // Try to find controller by ID first
            if (is_numeric($controller)) {
                $controllerUser = User::where('id', (int)$controller)
                    ->whereNotNull('fcm_token')
                    ->where('fcm_token', '!=', '')
                    ->first();
            }

            // If not found by ID, try by user_name or email
            if (!$controllerUser) {
                $controllerUser = User::where(function ($query) use ($controller) {
                    $query->where('user_name', $controller)
                        ->orWhere('email', $controller);
                })
                ->whereNotNull('fcm_token')
                ->where('fcm_token', '!=', '')
                ->first();
            }

            if (!$controllerUser) {
                Log::info('Controller user not found or has no FCM token', [
                    'task_id' => $task->id,
                    'controller' => $controller
                ]);
                return;
            }

            // Initialize Firebase messaging
            $messaging = $this->getMessaging();
            if (!$messaging) {
                Log::warning('Firebase messaging not available, cannot send notification to controller', [
                    'task_id' => $task->id,
                    'error' => $this->initializationError
                ]);
                return;
            }

            // Create notification
            $notification = Notification::create($title, $body);

            try {
                $message = CloudMessage::withTarget('token', $controllerUser->fcm_token)
                    ->withNotification($notification)
                    ->withData([
                        'title' => $title,
                        'body' => $body,
                        'task_id' => $task->id,
                        'task_name' => $task->name,
                        'task_step' => $task->step,
                        'user_name' => $controllerUser->user_name,
                    ]);

                $messaging->send($message);

                // Store notification for listing in Notification page
                TaskNotification::create([
                    'user_id' => $controllerUser->id,
                    'task_id' => $task->id,
                    'title' => $title,
                    'body' => $body,
                    'type' => 'task_status_updated',
                ]);

                Log::info('Notification sent to controller', [
                    'user_id' => $controllerUser->id,
                    'user_name' => $controllerUser->user_name,
                    'task_id' => $task->id,
                ]);
            } catch (\Exception $e) {
                Log::error('Failed to send notification to controller', [
                    'user_id' => $controllerUser->id,
                    'user_name' => $controllerUser->user_name,
                    'task_id' => $task->id,
                    'error' => $e->getMessage(),
                ]);
            }
        } catch (\Exception $e) {
            // Don't fail the task update if notification fails
            Log::error('Error sending notification to controller', [
                'task_id' => $task->id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Send notification to all users assigned to a task
     */
    private function sendNotificationToAssignedUsers(Task $task, string $title, string $body): void
    {
        try {
            // Parse users field (JSON string like "[2]" or "[2,3]" or "[\"2\"]")
            if (empty($task->users)) {
                Log::info('Task has no assigned users, skipping notification', ['task_id' => $task->id]);
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
                // Handle formats like "[2]", "[2,3]", "[\"2\"]"
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
                Log::warning('Firebase messaging not available, cannot send notifications', [
                    'task_id' => $task->id,
                    'error' => $this->initializationError
                ]);
                return;
            }

            // Create notification
            $notification = Notification::create($title, $body);

            $successCount = 0;
            $failedCount = 0;

            // Send notification to each user
            foreach ($users as $user) {
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
                        ]);

                    $messaging->send($message);
                    $successCount++;

                    // Store notification for listing in Notification page
                    TaskNotification::create([
                        'user_id' => $user->id,
                        'task_id' => $task->id,
                        'title' => $title,
                        'body' => $body,
                        'type' => $title === 'New Task Assigned'
                            ? 'task_assigned'
                            : 'task_status_updated',
                    ]);

                    Log::info('Notification sent to user', [
                        'user_id' => $user->id,
                        'user_name' => $user->user_name,
                        'task_id' => $task->id,
                    ]);
                } catch (\Exception $e) {
                    $failedCount++;
                    Log::error('Failed to send notification to user', [
                        'user_id' => $user->id,
                        'user_name' => $user->user_name,
                        'task_id' => $task->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            Log::info('Task notifications sent', [
                'task_id' => $task->id,
                'task_name' => $task->name,
                'success_count' => $successCount,
                'failed_count' => $failedCount,
                'total_users' => $users->count(),
            ]);
        } catch (\Exception $e) {
            // Don't fail the task creation/update if notification fails
            Log::error('Error sending task notifications', [
                'task_id' => $task->id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Request a delay for a task timeout notification
     * User can request a 6-minute delay after receiving a timeout notification
     * Maximum 5 delays allowed per user-task combination
     */
    public function requestDelay(Request $request, $id): JsonResponse
    {
        $user = $request->user();
        $task = Task::find($id);

        if (!$task) {
            return response()->json([
                'success' => false,
                'message' => 'Task not found.',
            ], 404);
        }

        // Check if user is assigned to this task
        $usersStr = $task->users;
        $userIds = [];
        $usersArray = json_decode($usersStr, true);
        if (is_array($usersArray)) {
            $userIds = array_map('intval', $usersArray);
        } else {
            preg_match_all('/["\']?(\d+)["\']?/', $usersStr, $matches);
            if (!empty($matches[1])) {
                $userIds = array_map('intval', $matches[1]);
            }
        }

        if (!in_array($user->id, $userIds)) {
            return response()->json([
                'success' => false,
                'message' => 'You are not assigned to this task.',
            ], 403);
        }

        // Check if task has timeout notification sent
        if (!$task->timeout_notified_at) {
            return response()->json([
                'success' => false,
                'message' => 'Task timeout notification has not been sent yet.',
            ], 400);
        }

        // Check existing delay for this user-task combination
        $existingDelay = Delay::where('user_id', (string)$user->id)
            ->where('task_id', (string)$task->id)
            ->first();

        // Get rest_max from task or delay
        $restMax = $task->rest_max ?? 0;
        if ($existingDelay && $existingDelay->rest_max !== null) {
            $restMax = $existingDelay->rest_max;
        }

        // Check if rest_max has reached 0 (no more delays allowed)
        if ($restMax <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'Maximum rest/delay limit has been reached for this task.',
                'data' => [
                    'rest_max' => $restMax,
                ],
            ], 400);
        }

        // Get rest_time from task or delay
        $restTime = $task->rest_time;
        if ($existingDelay && $existingDelay->rest_time) {
            $restTime = $existingDelay->rest_time;
        }

        // Decrement rest_max
        $newRestMax = $restMax - 1;
        $isLastTime = ($newRestMax == 1);

        // Calculate next alarm time based on rest_time
        $nextAlarmAt = null;
        if ($restTime) {
            $restTimeStr = $restTime;
            if (is_object($restTimeStr) && method_exists($restTimeStr, 'format')) {
                $restTimeStr = $restTimeStr->format('H:i:s');
            }
            
            // Parse rest_time (HH:mm:ss format)
            $restTimeParts = explode(':', $restTimeStr);
            if (count($restTimeParts) >= 2) {
                $hours = (int)$restTimeParts[0];
                $minutes = (int)$restTimeParts[1];
                $seconds = isset($restTimeParts[2]) ? (int)$restTimeParts[2] : 0;
                
                // Set next alarm time from now
                $nextAlarmAt = Carbon::now()->addHours($hours)->addMinutes($minutes)->addSeconds($seconds);
            }
        }

        if ($existingDelay) {
            // Update existing delay
            if ($restTime) {
                $existingDelay->rest_time = Carbon::parse($restTime);
            }
            $existingDelay->rest_max = $newRestMax;
            $existingDelay->next_alarm_at = $nextAlarmAt;
            $existingDelay->alarm_count = 0; // Reset alarm count when new delay is requested
            $existingDelay->last_alarm_at = null;
            $existingDelay->save();
        } else {
            // Create new delay
            $existingDelay = Delay::create([
                'user_id' => (string)$user->id,
                'task_id' => (string)$task->id,
                'rest_time' => $restTime ? Carbon::parse($restTime) : null,
                'rest_max' => $newRestMax,
                'next_alarm_at' => $nextAlarmAt,
                'alarm_count' => 0,
                'last_alarm_at' => null,
            ]);
        }

        // Reset timeout_notified_at so notification can be sent again
        $task->timeout_notified_at = null;
        $task->save();

        $message = 'Rest/delay requested successfully.';
        if ($isLastTime) {
            $message = '⚠️ LAST TIME: Rest/delay requested successfully. This is your last rest/delay opportunity.';
        }

        Log::info('Task delay requested', [
            'user_id' => $user->id,
            'task_id' => $task->id,
            'rest_max' => $existingDelay->rest_max,
            'is_last_time' => $isLastTime,
        ]);

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => [
                'delay_id' => $existingDelay->id,
                'rest_max' => $existingDelay->rest_max,
                'remaining_rests' => $existingDelay->rest_max,
                'is_last_time' => $isLastTime,
            ],
        ], 200);
    }

    /**
     * Delete a task.
     * Only super_admin or admin can delete tasks.
     */
    public function destroy(int $id): JsonResponse
    {
        $user = request()->user();

        // Check if user is super_admin or has admin role
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated',
            ], 401);
        }

        $isSuperAdmin = $user->type === 'super_admin';
        $hasAdminRole = $user->hasRole('admin', 'api');

        if (!$isSuperAdmin && !$hasAdminRole) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Only super admin or admin can delete tasks.',
            ], 403);
        }

        try {
            $task = Task::findOrFail($id);

            // Delete related records (refuses, delays, etc.)
            // Note: refuse table uses 'task' column (string), delays table uses 'task_id' column (string)
            Refuse::where('task', (string)$task->id)->delete();
            Delay::where('task_id', (string)$task->id)->delete();

            // Delete the task
            $task->delete();

            return response()->json([
                'success' => true,
                'message' => 'Task deleted successfully',
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Task not found',
            ], 404);
        } catch (\Exception $e) {
            Log::error('Error deleting task: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete task: ' . $e->getMessage(),
            ], 500);
        }
    }
}
