<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Enrollment;
use App\Models\Payment;
use App\Models\Lesson;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * StudentController - Deprecated
 *
 * This controller was designed for logged-in users with role='student'.
 * With the new architecture, students use StudentAuthController with name+phone verification.
 *
 * Consider using StudentAuthController instead for student-facing features.
 */
class StudentController extends Controller
{
    /**
     * Get student from X-Student-Token header
     */
    private function getStudentFromToken(Request $request): ?Student
    {
        $token = $request->header('X-Student-Token');
        if (!$token) {
            return null;
        }

        $sessionData = Cache::get("student_session:{$token}");
        if (!$sessionData) {
            return null;
        }

        return Student::find($sessionData['student_id']);
    }

    /**
     * Get student dashboard data
     */
    public function dashboard(Request $request)
    {
        $student = $this->getStudentFromToken($request);

        if (!$student) {
            return response()->json(['message' => '인증이 필요합니다.'], 401);
        }

        // Get active enrollments
        $enrollments = Enrollment::with(['lesson.branch'])
            ->where('student_id', $student->id)
            ->where('status', 'approved')
            ->where('expires_at', '>=', now())
            ->get();

        // Get recent attendances
        $recentAttendances = Attendance::where('student_id', $student->id)
            ->with('lesson')
            ->orderBy('lesson_date', 'desc')
            ->limit(5)
            ->get();

        // Calculate attendance statistics
        $totalLessons = 0;
        $attendedLessons = 0;
        $lateLessons = 0;

        foreach ($enrollments as $enrollment) {
            $stats = $this->getEnrollmentStats($enrollment);
            $totalLessons += $stats['total'];
            $attendedLessons += $stats['present'];
            $lateLessons += $stats['late'];
        }

        return response()->json([
            'enrollments' => $enrollments->map(fn($e) => [
                'id' => $e->id,
                'lesson' => [
                    'id' => $e->lesson->id,
                    'name' => $e->lesson->name,
                    'days_of_week' => $e->lesson->days_of_week,
                    'start_time' => $e->lesson->start_time,
                    'end_time' => $e->lesson->end_time,
                ],
                'remaining_sessions' => $e->remaining_sessions,
                'expires_at' => $e->expires_at->format('Y-m-d'),
            ]),
            'recent_attendances' => $recentAttendances->map(fn($a) => [
                'id' => $a->id,
                'lesson' => $a->lesson->name,
                'status' => $a->status,
                'date' => $a->lesson_date->format('Y-m-d'),
                'check_in_time' => $a->checked_at ? $a->checked_at->format('H:i') : null,
            ]),
            'statistics' => [
                'total_lessons' => $totalLessons,
                'attended_lessons' => $attendedLessons,
                'late_lessons' => $lateLessons,
                'attendance_rate' => $totalLessons > 0
                    ? round(($attendedLessons / $totalLessons) * 100, 1)
                    : 0,
            ],
        ]);
    }

    /**
     * Get student enrollments
     */
    public function enrollments(Request $request)
    {
        $student = $this->getStudentFromToken($request);

        if (!$student) {
            return response()->json(['message' => '인증이 필요합니다.'], 401);
        }

        $enrollments = Enrollment::with(['lesson'])
            ->where('student_id', $student->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'enrollments' => $enrollments->map(fn($e) => [
                'id' => $e->id,
                'lesson_name' => $e->lesson->name,
                'lesson_description' => $e->lesson->description,
                'days' => $e->lesson->days_of_week,
                'time' => $e->lesson->start_time . ' - ' . $e->lesson->end_time,
                'price' => $e->lesson->price,
                'status' => $e->status,
                'enrolled_at' => $e->enrolled_at ? $e->enrolled_at->format('Y-m-d') : $e->created_at->format('Y-m-d'),
            ]),
        ]);
    }

    /**
     * Get student attendance history
     */
    public function attendances(Request $request)
    {
        $student = $this->getStudentFromToken($request);

        if (!$student) {
            return response()->json(['message' => '인증이 필요합니다.'], 401);
        }

        $query = Attendance::where('student_id', $student->id)
            ->with('lesson');

        // Filter by month if provided
        if ($request->month) {
            $query->whereRaw('DATE_FORMAT(lesson_date, "%Y-%m") = ?', [$request->month]);
        }

        $attendances = $query->orderBy('lesson_date', 'desc')->get();

        return response()->json([
            'attendances' => $attendances->map(fn($a) => [
                'id' => $a->id,
                'lesson_name' => $a->lesson->name,
                'date' => $a->lesson_date->format('Y-m-d'),
                'status' => $a->status,
                'check_in_time' => $a->checked_at ? $a->checked_at->format('H:i') : null,
                'note' => $a->note,
            ]),
        ]);
    }

    /**
     * Get student payment history
     */
    public function payments(Request $request)
    {
        $student = $this->getStudentFromToken($request);

        if (!$student) {
            return response()->json(['message' => '인증이 필요합니다.'], 401);
        }

        $payments = Payment::whereHas('enrollment', function ($q) use ($student) {
            $q->where('student_id', $student->id);
        })
            ->with('enrollment.lesson')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'payments' => $payments->map(fn($p) => [
                'id' => $p->id,
                'lesson' => $p->enrollment->lesson->name,
                'type' => $p->type,
                'amount' => $p->amount,
                'status' => $p->status,
                'paid_at' => $p->paid_at?->format('Y-m-d'),
                'refunded_at' => $p->refunded_at?->format('Y-m-d'),
            ]),
        ]);
    }

    /**
     * Get available lessons for enrollment
     */
    public function availableLessons(Request $request)
    {
        $student = $this->getStudentFromToken($request);

        if (!$student) {
            return response()->json(['message' => '인증이 필요합니다.'], 401);
        }

        $lessons = Lesson::where('branch_id', $student->branch_id)
            ->where('is_active', true)
            ->withCount(['enrollments' => function ($q) {
                $q->where('status', 'approved');
            }])
            ->get();

        return response()->json([
            'lessons' => $lessons->map(fn($l) => [
                'id' => $l->id,
                'name' => $l->name,
                'description' => $l->description,
                'days_of_week' => $l->days_of_week,
                'start_time' => $l->start_time,
                'end_time' => $l->end_time,
                'price' => $l->price,
                'max_students' => $l->max_students,
                'current_students' => $l->enrollments_count,
                'is_full' => $l->enrollments_count >= $l->max_students,
            ]),
        ]);
    }

    /**
     * Request enrollment for a lesson
     */
    public function requestEnrollment(Request $request)
    {
        $student = $this->getStudentFromToken($request);

        if (!$student) {
            return response()->json(['message' => '인증이 필요합니다.'], 401);
        }

        $validated = $request->validate([
            'lesson_id' => 'required|exists:lessons,id',
        ]);

        $lesson = Lesson::findOrFail($validated['lesson_id']);

        // Check if already enrolled
        $existingEnrollment = Enrollment::where('student_id', $student->id)
            ->where('lesson_id', $lesson->id)
            ->whereIn('status', ['pending', 'approved', 'waitlisted'])
            ->first();

        if ($existingEnrollment) {
            return response()->json([
                'message' => '이미 수강 신청한 수업입니다.',
            ], 400);
        }

        // Check capacity
        $currentCount = Enrollment::where('lesson_id', $lesson->id)
            ->where('status', 'approved')
            ->count();

        $status = $currentCount >= $lesson->max_students ? 'waitlisted' : 'pending';
        $waitlistPosition = null;

        if ($status === 'waitlisted') {
            $waitlistPosition = Enrollment::where('lesson_id', $lesson->id)
                    ->where('status', 'waitlisted')
                    ->max('waitlist_position') + 1;
        }

        $enrollment = Enrollment::create([
            'student_id' => $student->id,
            'lesson_id' => $lesson->id,
            'branch_id' => $lesson->branch_id,
            'status' => $status,
            'enrolled_at' => now(),
            'waitlist_position' => $waitlistPosition,
        ]);

        $message = $status === 'waitlisted'
            ? "대기자 {$waitlistPosition}번으로 등록되었습니다."
            : '수강 신청이 완료되었습니다. 관리자 승인 후 수강 가능합니다.';

        return response()->json([
            'message' => $message,
            'enrollment' => [
                'id' => $enrollment->id,
                'status' => $enrollment->status,
                'waitlist_position' => $enrollment->waitlist_position,
            ],
        ], 201);
    }

    /**
     * Get enrollment statistics
     */
    private function getEnrollmentStats(Enrollment $enrollment): array
    {
        $attendances = Attendance::where('enrollment_id', $enrollment->id)->get();

        return [
            'total' => $attendances->count(),
            'present' => $attendances->where('status', 'present')->count(),
            'late' => $attendances->where('status', 'late')->count(),
            'absent' => $attendances->where('status', 'absent')->count(),
            'excused' => $attendances->where('status', 'excused')->count(),
        ];
    }
}
