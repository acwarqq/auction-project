<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\LoginRequest;
use App\Http\Requests\Api\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    /**
     * Register a new user with the default (pending) status.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        // Only fillable attributes (name, email, password) go through mass assignment.
        $user = User::create($request->validated());

        // role and status are not fillable - assign them explicitly.
        $user->role = UserRole::User;
        $user->status = UserStatus::Pending;
        $user->save();

        return response()->json([
            'data' => new UserResource($user),
        ], 201);
    }

    /**
     * Authenticate an approved user (session/cookie mode for the SPA).
     */
    public function login(LoginRequest $request): JsonResponse
    {
        // Sanctum activates session middleware only for requests "from the
        // frontend" (Referer/Origin matching sanctum.stateful). A headerless
        // client (e.g. plain curl) gets a 419 instead of a 500 below.
        abort_unless(
            $request->hasSession(),
            419,
            'Session is unavailable: send a Referer or Origin header matching SANCTUM_STATEFUL_DOMAINS.'
        );

        if (! Auth::guard('web')->attempt($request->validated(), remember: false)) {
            abort(401, 'Invalid credentials.');
        }

        $user = Auth::guard('web')->user();

        if ($user->status !== UserStatus::Approved) {
            $status = $user->status->value;

            // Never leave an unapproved user authenticated.
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return response()->json([
                'message' => sprintf('Login is not allowed: account status is "%s".', $status),
                'status' => $status,
            ], 403);
        }

        $request->session()->regenerate();

        return response()->json([
            'data' => new UserResource($user),
        ]);
    }

    /**
     * End the authenticated session.
     */
    public function logout(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json([
            'message' => 'Logged out.',
        ]);
    }

    /**
     * Return the currently authenticated user.
     */
    public function me(Request $request): UserResource
    {
        return new UserResource($request->user());
    }
}
