<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
            'phone'    => 'nullable|string|max:50',
            'role'     => 'nullable|string|in:user,dealer,retail,wholesale,agent',
            'company'  => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors'  => $validator->errors()
            ], 422);
        }

        $role = $request->input('role', 'user');
        if ($role === 'retail') $role = 'user';
        if ($role === 'wholesale' || $role === 'agent') $role = 'dealer_user';

        $userId = DB::table('users')->insertGetId([
            'name'              => $request->name,
            'email'             => $request->email,
            'phone'             => $request->phone,
            'password'          => Hash::make($request->password),
            'role'              => $role,
            'status'            => 'active',
            'customer_group_id' => ($role === 'dealer_user' || $role === 'dealer') ? 2 : 1,
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);

        $user = DB::table('users')->where('id', $userId)->first();
        $token = bin2hex(random_bytes(32));

        return response()->json([
            'success' => true,
            'message' => 'Registration successful',
            'token'   => $token,
            'user'    => [
                'id'    => $user->id,
                'name'  => $user->name,
                'email' => $user->email,
                'role'  => $user->role,
                'phone' => $user->phone,
            ]
        ], 201);
    }

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
                'errors'  => $validator->errors()
            ], 422);
        }

        $user = DB::table('users')->where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid email or password.'
            ], 401);
        }

        $token = bin2hex(random_bytes(32));

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'token'   => $token,
            'user'    => [
                'id'       => $user->id,
                'name'     => $user->name,
                'email'    => $user->email,
                'role'     => $user->role,
                'is_admin' => in_array($user->role, ['super_admin', 'admin', 'operations']),
                'phone'    => $user->phone,
            ]
        ]);
    }

    public function me(Request $request)
    {
        $user = DB::table('users')->where('email', $request->query('email'))->first();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'User not found'], 404);
        }

        return response()->json([
            'success' => true,
            'user'    => [
                'id'       => $user->id,
                'name'     => $user->name,
                'email'    => $user->email,
                'role'     => $user->role,
                'is_admin' => in_array($user->role, ['super_admin', 'admin', 'operations']),
                'phone'    => $user->phone,
            ]
        ]);
    }
}
