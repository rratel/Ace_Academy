<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\AuditLog;
use App\Services\QrTokenService;
use Illuminate\Http\Request;
use Carbon\Carbon;

class QrController extends Controller
{
    private QrTokenService $qrTokenService;

    public function __construct(QrTokenService $qrTokenService)
    {
        $this->qrTokenService = $qrTokenService;
    }

    /**
     * Validate QR token and record attendance
     */
    public function validateToken(Request $request)
    {
        $validated = $request->validate([
            'token' => 'required|string',
            'lesson_id' => 'nullable|exists:lessons,id',
        ]);

        // Validate token
        $tokenData = $this->qrTokenService->validateToken($validated['token']);

        if (!$tokenData) {
            return response()->json([
                'success' => false,
                'message' => '유효하지 않거나 만료된 QR 코드입니다.',
            ], 400);
        }

        // Get student_id from token data
        $studentId = $tokenData['student_id'] ?? $tokenData['user_id'] ?? null;
        $studentName = $tokenData['student_name'] ?? $tokenData['user_name'] ?? 'Unknown';

        if (!$studentId) {
            return response()->json([
                'success' => false,
                'message' => '유효하지 않은 토큰입니다.',
            ], 400);
        }

        $now = Carbon::now();

        // Find active enrollment for today
        $enrollment = $this->findActiveEnrollment($studentId, $validated['lesson_id'] ?? null, $now);

        if (!$enrollment) {
            return response()->json([
                'success' => false,
                'message' => '오늘 예정된 수업이 없습니다.',
                'student_name' => $studentName,
            ], 400);
        }

        $lesson = $enrollment->lesson;

        // Check for duplicate attendance
        $existingAttendance = Attendance::where('enrollment_id', $enrollment->id)
            ->where('lesson_date', $now->toDateString())
            ->first();

        if ($existingAttendance) {
            return response()->json([
                'success' => false,
                'message' => '이미 출석 처리되었습니다.',
                'student_name' => $studentName,
                'lesson' => $lesson->name,
                'attendance_time' => $existingAttendance->checked_at ? $existingAttendance->checked_at->format('H:i') : '-',
            ], 400);
        }

        // Determine attendance status (present or late)
        $status = $this->determineAttendanceStatus($lesson, $now);
        $lateMinutes = $status === 'late' ? $this->calculateLateMinutes($lesson, $now) : 0;

        // Create attendance record
        $attendance = Attendance::create([
            'enrollment_id' => $enrollment->id,
            'branch_id' => $lesson->branch_id,
            'student_id' => $studentId,
            'lesson_id' => $lesson->id,
            'lesson_date' => $now->toDateString(),
            'checked_at' => $now,
            'status' => $status,
            'late_minutes' => $lateMinutes,
        ]);

        AuditLog::log('attendance.qr_check_in', $attendance, null, [
            'student_id' => $studentId,
            'lesson_id' => $lesson->id,
            'status' => $status,
        ]);

        // TODO: Send Kakao Alimtalk notification to parents

        return response()->json([
            'success' => true,
            'message' => $status === 'present' ? '출석 완료' : '지각 처리됨',
            'student_name' => $studentName,
            'lesson' => $lesson->name,
            'status' => $status,
            'attendance_time' => $now->format('H:i'),
        ]);
    }

    /**
     * Find active enrollment for student
     */
    private function findActiveEnrollment(int $studentId, ?int $lessonId, Carbon $now): ?Enrollment
    {
        $dayOfWeek = strtolower($now->format('l')); // monday, tuesday, etc.

        $query = Enrollment::with('lesson')
            ->where('student_id', $studentId)
            ->where('status', 'approved')
            ->where('expires_at', '>=', $now)
            ->whereHas('lesson', function ($q) use ($dayOfWeek, $now, $lessonId) {
                $q->where('is_active', true)
                    ->whereJsonContains('days', $dayOfWeek);

                if ($lessonId) {
                    $q->where('id', $lessonId);
                }

                // Check if within lesson time window (1 hour before to end time)
                $q->where(function ($timeQuery) use ($now) {
                    $currentTime = $now->format('H:i:s');
                    $oneHourAgo = $now->copy()->subHour()->format('H:i:s');

                    $timeQuery->whereRaw('start_time <= ?', [$currentTime])
                        ->whereRaw('end_time >= ?', [$oneHourAgo]);
                });
            });

        return $query->first();
    }

    /**
     * Determine if attendance is on-time or late
     */
    private function determineAttendanceStatus(Lesson $lesson, Carbon $now): string
    {
        $lateThreshold = 15; // minutes

        $currentTime = Carbon::parse($now->format('H:i:s'));
        $lessonStartTime = Carbon::parse($lesson->start_time);

        // Calculate how many minutes past lesson start time
        // Positive value means student arrived after lesson start
        $minutesLate = $currentTime->diffInMinutes($lessonStartTime);

        // Check if current time is after lesson start
        if ($currentTime->gt($lessonStartTime) && $minutesLate > $lateThreshold) {
            return 'late';
        }

        return 'present';
    }

    /**
     * Calculate late minutes
     */
    private function calculateLateMinutes(Lesson $lesson, Carbon $now): int
    {
        $currentTime = Carbon::parse($now->format('H:i:s'));
        $lessonStartTime = Carbon::parse($lesson->start_time);

        // Only count late minutes if student arrived after lesson start
        if ($currentTime->lte($lessonStartTime)) {
            return 0;
        }

        return $currentTime->diffInMinutes($lessonStartTime);
    }
}
