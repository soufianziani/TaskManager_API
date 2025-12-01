<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Artisan;

class TaskTimeoutController extends Controller
{
    /**
     * Manually trigger the task timeout check.
     *
     * This wraps the existing `tasks:check-timeouts` console command
     * so it can be called from the API (for debugging or manual runs).
     */
    public function check(): JsonResponse
    {
        // Run the console command
        $exitCode = Artisan::call('tasks:check-timeouts');
        $output = Artisan::output();

        return response()->json([
            'success' => $exitCode === 0,
            'exit_code' => $exitCode,
            'output' => $output,
        ]);
    }
}


