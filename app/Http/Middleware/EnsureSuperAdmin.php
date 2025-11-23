<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSuperAdmin
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated',
            ], 401);
        }

        // Check only if user type is super_admin (no role check needed)
        if ($user->type !== 'super_admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Super Admin access required.',
                'data' => [
                    'user_type' => $user->type,
                    'required_type' => 'super_admin',
                ],
            ], 403);
        }

        return $next($request);
    }
}
