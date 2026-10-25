<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PricingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    /**
     * Register a customer account.
     *
     * The chosen account type decides the customer group, which in turn
     * decides exactly which products and prices the customer may see.
     */
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
            'phone'    => 'nullable|string|max:50',
            'company'  => 'nullable|string|max:255',
            'account_type' => 'nullable|in:retail,wholesale,agent',
            'role'     => 'nullable|in:retail,wholesale,agent,user,dealer,dealer_user',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors'  => $validator->errors(),
            ], 422);
        }

        $accountType = $this->resolveAccountType($request);
        $groupId = PricingService::GROUP_IDS[$accountType] ?? 1;

        $userId = DB::table('users')->insertGetId([
            'name'              => $request->name,
            'email'             => $request->email,
            'phone'             => $request->phone,
            'password'          => Hash::make($request->password),
            'role'              => $accountType,
            'customer_group_id' => $groupId,
            'status'            => 'active',
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);

        $token = $this->issueToken($userId);

        return response()->json([
            'success' => true,
            'message' => 'Registration successful',
            'token'   => $token,
            'user'    => $this->userPayload(DB::table('users')->where('id', $userId)->first()),
        ], 201);
    }

    /**
     * Exchange credentials for a bearer token.
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $user = DB::table('users')->where('email', $request->email)->whereNull('deleted_at')->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid email or password.'
            ], 401);
        }

        if ($user->status !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'This account is not active. Please contact DroneHab.'
            ], 403);
        }

        DB::table('users')->where('id', $user->id)->update(['last_login_at' => now()]);

        $token = $this->issueToken($user->id);

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'token'   => $token,
            'user'    => $this->userPayload($user),
        ]);
    }

    /**
     * Current session details (token based — no email query parameter).
     */
    public function me(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['success' => false, 'message' => 'User not found'], 404);
        }

        return response()->json([
            'success' => true,
            'user'    => $this->userPayload($user),
        ]);
    }

    /**
     * Revoke the current bearer token.
     */
    public function logout(Request $request)
    {
        $tokenId = $request->attributes->get('auth_token_id');

        if ($tokenId) {
            DB::table('api_tokens')->where('id', $tokenId)->delete();
        }

        return response()->json([
            'success' => true,
            'message' => 'Signed out successfully',
        ]);
    }

    // ─────────────────────────────────────────────
    // Internals
    // ─────────────────────────────────────────────

    /**
     * Create and persist a new bearer token (only the hash is stored).
     */
    private function issueToken(int $userId): string
    {
        $token = bin2hex(random_bytes(32));

        DB::table('api_tokens')->insert([
            'user_id'     => $userId,
            'token_hash'  => hash('sha256', $token),
            'name'        => 'spa',
            'last_used_at' => now(),
            'expires_at'  => now()->addDays(30),
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        return $token;
    }

    /**
     * Normalise the requested account type onto retail / wholesale / agent.
     */
    private function resolveAccountType(Request $request): string
    {
        $requested = strtolower((string) $request->input('account_type', $request->input('role', 'retail')));

        return match ($requested) {
            'wholesale', 'dealer', 'dealer_user' => 'wholesale',
            'agent', 'service_agent'            => 'agent',
            default                              => 'retail',
        };
    }

    /**
     * Shape the user payload the SPA stores in localStorage.
     */
    private function userPayload(?object $user): array
    {
        if (!$user) {
            return [];
        }

        $group = DB::table('customer_groups')->where('id', $user->customer_group_id ?: 1)->first();
        $groupId = (int) ($group->id ?? 1);
        $roleKey = PricingService::roleKeyFor($groupId) ?? 'normal';

        return [
            'id'                 => $user->id,
            'name'               => $user->name,
            'email'              => $user->email,
            'phone'              => $user->phone,
            'role'               => $roleKey,
            'group_slug'         => $group->slug ?? 'retail',
            'db_role'            => $user->role,
            'customer_group_id'  => $groupId,
            'is_admin'           => in_array($user->role, ['super_admin', 'admin', 'operations'], true),
            'is_wholesale'       => $roleKey === 'wholesale',
            'is_agent'           => $roleKey === 'agent',
        ];
    }
}
