<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Register a new user
     */
    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'phone' => 'required|string|max:20',
            'role' => 'required|in:student,parent',
            'branch_id' => 'required|exists:branches,id',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'phone' => $validated['phone'],
            'role' => $validated['role'],
            'branch_id' => $validated['branch_id'],
            'status' => 'pending', // Requires admin approval
        ]);

        AuditLog::log('user.register', $user, null, $user->toArray());

        return response()->json([
            'message' => '회원가입이 완료되었습니다. 관리자 승인 후 이용 가능합니다.',
            'user' => $user->only(['id', 'name', 'email', 'role', 'status']),
        ], 201);
    }

    /**
     * Login user and create token
     */
    public function login(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (!$user || !Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['이메일 또는 비밀번호가 올바르지 않습니다.'],
            ]);
        }

        if ($user->status === 'pending') {
            return response()->json([
                'message' => '관리자 승인 대기 중입니다.',
                'status' => 'pending',
            ], 403);
        }

        if ($user->status === 'rejected') {
            return response()->json([
                'message' => '가입이 거절되었습니다. 관리자에게 문의해주세요.',
                'status' => 'rejected',
            ], 403);
        }

        if ($user->status === 'inactive') {
            return response()->json([
                'message' => '비활성화된 계정입니다. 관리자에게 문의해주세요.',
                'status' => 'inactive',
            ], 403);
        }

        // Delete existing tokens
        $user->tokens()->delete();

        // Create new token
        $token = $user->createToken('auth-token')->plainTextToken;

        AuditLog::log('user.login', $user, null, ['ip' => $request->ip()]);

        return response()->json([
            'message' => '로그인 성공',
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'branch_id' => $user->branch_id,
                'branch' => $user->branch?->only(['id', 'name', 'code']),
            ],
        ]);
    }

    /**
     * Get authenticated user info
     */
    public function me(Request $request)
    {
        $user = $request->user();
        $user->load('branch');

        $data = [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'role' => $user->role,
            'status' => $user->status,
            'branch_id' => $user->branch_id,
            'branch' => $user->branch?->only(['id', 'name', 'code']),
        ];

        // Include children for parent role
        if ($user->role === 'parent') {
            $data['children'] = $user->children()->with('branch')->get()->map(fn($child) => [
                'id' => $child->id,
                'name' => $child->name,
                'email' => $child->email,
                'branch' => $child->branch?->only(['id', 'name']),
            ]);
        }

        return response()->json($data);
    }

    /**
     * Logout user (revoke current token)
     */
    public function logout(Request $request)
    {
        $user = $request->user();

        AuditLog::log('user.logout', $user, null, ['ip' => $request->ip()]);

        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => '로그아웃 되었습니다.',
        ]);
    }

    /**
     * Refresh token
     */
    public function refresh(Request $request)
    {
        $user = $request->user();

        // Delete current token
        $request->user()->currentAccessToken()->delete();

        // Create new token
        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'token' => $token,
        ]);
    }
}
