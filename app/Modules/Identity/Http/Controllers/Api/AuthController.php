<?php

namespace App\Modules\Identity\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(
        protected AuthService $authService
    ) {}

    /**
     * Authenticate user and return token.
     */
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        try {
            $user = $this->authService->login(
                username: $validated['username'],
                password: $validated['password'],
            );

            $token = $user->createToken('api')->plainTextToken;

            return response()->json([
                'success' => true,
                'data' => [
                    'token' => $token,
                    'user' => [
                        'id' => $user->id,
                        'username' => $user->username,
                        'full_name' => $user->full_name,
                        'assigned_branch_id' => $user->assigned_branch_id,
                        'roles' => $user->roles->pluck('name'),
                        'active_role' => $user->activeRole?->name,
                    ],
                ],
            ], 200);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'invalid_credentials',
                    'message' => $e->getMessage(),
                ],
            ], 422);
        }
    }

    /**
     * Logout the current user (revoke token).
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'data' => null,
        ]);
    }

    /**
     * Get current user profile.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $user->id,
                'username' => $user->username,
                'full_name' => $user->full_name,
                'assigned_branch' => $user->assignedBranch,
                'roles' => $user->roles,
                'active_role' => $user->activeRole,
                'is_online_channel' => $user->is_online_channel,
            ],
        ]);
    }

    /**
     * Switch active role.
     */
    public function switchRole(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'role_id' => ['required', 'integer', 'exists:roles,id'],
        ]);

        try {
            $user = $this->authService->switchRole($validated['role_id']);

            return response()->json([
                'success' => true,
                'data' => [
                    'active_role' => $user->activeRole,
                ],
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'role_switch_failed',
                    'message' => $e->getMessage(),
                ],
            ], 422);
        }
    }
}
