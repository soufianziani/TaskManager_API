<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateTaskRequest;
use App\Models\Task;
use Illuminate\Http\JsonResponse;

class TaskController extends Controller
{
    /**
     * Create a new task.
     * Only admin and super_admin users can create tasks.
     */
    public function create(CreateTaskRequest $request): JsonResponse
    {
        $user = $request->user();

        // Verify user type is admin or super_admin
        if (!in_array($user->type, ['admin', 'super_admin'])) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Only Admin or Super Admin can create tasks.',
                'data' => [
                    'user_type' => $user->type,
                    'required_types' => ['admin', 'super_admin'],
                ],
            ], 403);
        }

        $task = Task::create([
            'name' => $request->name,
            'description' => $request->description,
            'type_id' => $request->type_id,
            'department_id' => $request->department_id,
            'category_id' => $request->category_id,
            'justif_type' => $request->justif_type,
            'duration' => $request->duration,
            'period_start' => $request->period_start,
            'period_end' => $request->period_end,
            'period_type' => $request->period_type,
            'created_by' => $user->id,
        ]);

        $task->load(['type', 'department', 'category', 'creator']);

        return response()->json([
            'success' => true,
            'message' => 'Task created successfully',
            'data' => $task,
        ], 201);
    }
}
