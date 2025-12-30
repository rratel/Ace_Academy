<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\AuditLog;
use App\Services\QrTokenService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
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
            Log::warning('[QR] 토큰 검증 실패', ['token' => substr($validated['token'], 0, 20) . '...']);
            return response()->json([
                'success' => false,
                'message' => '유효하지 않거나 만료된 QR 코드입니다.',
            ], 400);
        }

        // Get student_id from token data
        $studentId = $tokenData['student_id'] ?? $tokenData['user_id'] ?? null;
        $studentName = $tokenData['student_name'] ?? $tokenData['user_name'] ?? 'Unknown';

        Log::info('[QR] 토큰 검증 성공', [
            'student_id' => $studentId,
            'student_name' => $studentName,
            'token_data' => $tokenData,
        ]);

        if (!$studentId) {
            return response()->json([
                'success' => false,
                'message' => '유효하지 않은 토큰입니다.',
            ], 400);
        }

        $now = Carbon::now();

        Log::info('[QR] 시간 정보', [
            'now' => $now->toDateTimeString(),
            'timezone' => $now->timezone->getName(),
            'day_of_week' => strtolower($now->format('l')),
            'current_time' => $now->format('H:i:s'),
        ]);

        // Find active enrollment for today
        $enrollment = $this->findActiveEnrollment($studentId, $validated['lesson_id'] ?? null, $now);

        if (!$enrollment) {
            // 디버그: 왜 등록을 못 찾는지 상세 로그
            $this->logEnrollmentDebug($studentId, $now);

            return response()->json([
                'success' => false,
                'message' => '오늘 예정된 수업이 없습니다.',
                'student_name' => $studentName,
                'debug' => [
                    'server_time' => $now->toDateTimeString(),
                    'timezone' => $now->timezone->getName(),
                    'day_of_week' => strtolower($now->format('l')),
                ],
            ], 400);
        }

        $lesson = $enrollment->lesson;

        Log::info('[QR] 수강 정보 찾음', [
            'enrollment_id' => $enrollment->id,
            'lesson_id' => $lesson->id,
            'lesson_name' => $lesson->title,
        ]);

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

        Log::info('[QR] 출석 기록 생성', [
            'attendance_id' => $attendance->id,
            'status' => $status,
            'late_minutes' => $lateMinutes,
        ]);

        AuditLog::log('attendance.qr_check_in', $attendance, null, [
            'student_id' => $studentId,
            'lesson_id' => $lesson->id,
            'status' => $status,
        ]);

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
     * Debug: Log why enrollment was not found
     */
    private function logEnrollmentDebug(int $studentId, Carbon $now): void
    {
        $dayOfWeek = strtolower($now->format('l'));
        $currentTime = $now->format('H:i:s');

        // 1. 학생의 모든 등록 확인
        $allEnrollments = Enrollment::with('lesson')
            ->where('student_id', $studentId)
            ->get();

        Log::warning('[QR 디버그] 학생의 전체 수강 등록', [
            'student_id' => $studentId,
            'total_enrollments' => $allEnrollments->count(),
            'enrollments' => $allEnrollments->map(fn($e) => [
                'id' => $e->id,
                'lesson_id' => $e->lesson_id,
                'lesson_name' => $e->lesson?->title,
                'status' => $e->status,
                'expires_at' => $e->expires_at?->toDateString(),
            ])->toArray(),
        ]);

        // 2. 승인된 등록만 확인
        $approvedEnrollments = Enrollment::with('lesson')
            ->where('student_id', $studentId)
            ->where('status', 'approved')
            ->get();

        Log::warning('[QR 디버그] 승인된 수강 등록', [
            'approved_count' => $approvedEnrollments->count(),
        ]);

        // 3. 만료되지 않은 등록 확인
        $notExpired = Enrollment::with('lesson')
            ->where('student_id', $studentId)
            ->where('status', 'approved')
            ->where('expires_at', '>=', $now)
            ->get();

        Log::warning('[QR 디버그] 만료되지 않은 수강', [
            'not_expired_count' => $notExpired->count(),
            'current_date' => $now->toDateString(),
        ]);

        // 4. 요일 체크
        foreach ($notExpired as $enrollment) {
            $lesson = $enrollment->lesson;
            if (!$lesson) continue;

            $lessonDays = $lesson->days ?? [];
            $matchesDay = in_array($dayOfWeek, $lessonDays);

            Log::warning('[QR 디버그] 수업 요일 체크', [
                'lesson_id' => $lesson->id,
                'lesson_name' => $lesson->title,
                'lesson_days' => $lessonDays,
                'today' => $dayOfWeek,
                'matches_day' => $matchesDay,
                'is_active' => $lesson->is_active,
            ]);

            // 5. 시간 체크
            if ($matchesDay && $lesson->is_active) {
                $startTime = $lesson->start_time;
                $endTime = $lesson->end_time;
                $oneHourAgo = $now->copy()->subHour()->format('H:i:s');

                Log::warning('[QR 디버그] 시간 체크', [
                    'lesson_start' => $startTime,
                    'lesson_end' => $endTime,
                    'current_time' => $currentTime,
                    'one_hour_ago' => $oneHourAgo,
                    'condition_1' => "start_time($startTime) <= current($currentTime)",
                    'condition_2' => "end_time($endTime) >= one_hour_ago($oneHourAgo)",
                ]);
            }
        }
    }

    /**
     * Find active enrollment for student
     */
    private function findActiveEnrollment(int $studentId, ?int $lessonId, Carbon $now): ?Enrollment
    {
        $dayOfWeek = strtolower($now->format('l')); // monday, tuesday, etc.
        $currentTime = $now->format('H:i:s');

        Log::info('[QR] findActiveEnrollment 시작', [
            'student_id' => $studentId,
            'lesson_id' => $lessonId,
            'day_of_week' => $dayOfWeek,
            'current_time' => $currentTime,
        ]);

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
                // 시간 체크 제거 - 테스트를 위해 일단 비활성화
                // $q->where(function ($timeQuery) use ($now) {
                //     $currentTime = $now->format('H:i:s');
                //     $oneHourAgo = $now->copy()->subHour()->format('H:i:s');
                //
                //     $timeQuery->whereRaw('start_time <= ?', [$currentTime])
                //         ->whereRaw('end_time >= ?', [$oneHourAgo]);
                // });
            });

        $result = $query->first();

        Log::info('[QR] findActiveEnrollment 결과', [
            'found' => $result !== null,
            'enrollment_id' => $result?->id,
        ]);

        return $result;
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
