<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Services\QrTokenService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class StudentAuthController extends Controller
{
    private QrTokenService $qrTokenService;

    public function __construct(QrTokenService $qrTokenService)
    {
        $this->qrTokenService = $qrTokenService;
    }

    /**
     * Verify student by name and phone
     */
    public function verify(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:50',
            'phone' => 'required|string|max:20',
        ]);

        // Normalize phone number (remove dashes and spaces)
        $phone = preg_replace('/[^0-9]/', '', $validated['phone']);

        // Find student
        $student = Student::where('phone', $phone)
            ->where('name', $validated['name'])
            ->where('status', 'active')
            ->first();

        if (!$student) {
            return response()->json([
                'success' => false,
                'message' => '등록된 학생 정보를 찾을 수 없습니다.',
            ], 404);
        }

        // Generate session token (valid for 1 hour)
        $sessionToken = Str::random(64);
        $expiresAt = now()->addHour();

        // Store session in cache
        Cache::put("student_session:{$sessionToken}", [
            'student_id' => $student->id,
            'student_name' => $student->name,
            'branch_id' => $student->branch_id,
        ], $expiresAt);

        return response()->json([
            'success' => true,
            'message' => '인증 성공',
            'session_token' => $sessionToken,
            'expires_at' => $expiresAt->toIso8601String(),
            'student' => [
                'id' => $student->id,
                'name' => $student->name,
                'branch' => $student->branch?->name,
            ],
        ]);
    }

    /**
     * Get student info from session token
     */
    public function me(Request $request)
    {
        $sessionToken = $request->header('X-Student-Token');

        if (!$sessionToken) {
            return response()->json([
                'message' => '세션 토큰이 필요합니다.',
            ], 401);
        }

        $sessionData = Cache::get("student_session:{$sessionToken}");

        if (!$sessionData) {
            return response()->json([
                'message' => '세션이 만료되었습니다. 다시 인증해주세요.',
            ], 401);
        }

        $student = Student::with('branch')->find($sessionData['student_id']);

        if (!$student || !$student->isActive()) {
            Cache::forget("student_session:{$sessionToken}");
            return response()->json([
                'message' => '학생 정보를 찾을 수 없습니다.',
            ], 404);
        }

        return response()->json([
            'student' => [
                'id' => $student->id,
                'name' => $student->name,
                'phone' => $student->formatted_phone,
                'branch' => $student->branch?->name,
            ],
        ]);
    }

    /**
     * Generate QR token for student
     */
    public function generateQrToken(Request $request)
    {
        $sessionToken = $request->header('X-Student-Token');

        if (!$sessionToken) {
            return response()->json([
                'message' => '세션 토큰이 필요합니다.',
            ], 401);
        }

        $sessionData = Cache::get("student_session:{$sessionToken}");

        if (!$sessionData) {
            return response()->json([
                'message' => '세션이 만료되었습니다. 다시 인증해주세요.',
            ], 401);
        }

        $student = Student::find($sessionData['student_id']);

        if (!$student || !$student->isActive()) {
            return response()->json([
                'message' => '학생 정보를 찾을 수 없습니다.',
            ], 404);
        }

        // Generate QR token using QrTokenService
        $tokenData = $this->qrTokenService->generateTokenForStudent($student);

        return response()->json([
            'token' => $tokenData['token'],
            'expires_in' => $tokenData['expires_in'],
            'valid_until' => $tokenData['valid_until'],
        ]);
    }

    /**
     * Logout (invalidate session)
     */
    public function logout(Request $request)
    {
        $sessionToken = $request->header('X-Student-Token');

        if ($sessionToken) {
            Cache::forget("student_session:{$sessionToken}");
        }

        return response()->json([
            'message' => '로그아웃 되었습니다.',
        ]);
    }
}
