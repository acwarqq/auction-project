<?php

namespace App\Http\Middleware;

use App\Enums\UserStatus;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Allows only approved users through (registered route middleware alias: "approved").
 */
class EnsureUserIsApproved
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->status !== UserStatus::Approved) {
            return response()->json([
                'message' => 'Your account has not been approved yet.',
            ], 403);
        }

        return $next($request);
    }
}
