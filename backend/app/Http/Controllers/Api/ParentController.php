<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Enrollment;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Http\Request;

class ParentController extends Controller
{
    /**
     * Get parent dashboard with children overview
     */
    public function dashboard(Request $request)
    {
        $user = $request->user();
        $children = $user->children()->with('branch')->get();

        $childrenData = $children->map(function ($child) {
            $enrollmentsCount = Enrollment::where('student_id', $child->id)
                ->where('status', 'approved')
                ->count();

            $recentAttendance = Attendance::where('student_id', $child->id)
                ->with('lesson')
                ->orderBy('lesson_date', 'desc')
                ->first();

            return [
                'id' => $child->id,
                'name' => $child->name,
                'phone' => $child->phone,
                'enrollments_count' => $enrollmentsCount,
                'recent_attendance' => $recentAttendance ? [
                    'date' => $recentAttendance->lesson_date->format('Y-m-d'),
                    'status' => $recentAttendance->status,
                ] : null,
            ];
        });

        return response()->json([
            'children' => $childrenData,
        ]);
    }

    /**
     * Get detailed info for a specific child
     */
    public function childDetail(Request $request, int $childId)
    {
        $user = $request->user();

        // Verify parent-child relationship
        $child = $user->children()->find($childId);

        if (!$child) {
            return response()->json([
                'message' => '자녀 정보를 찾을 수 없습니다.',
            ], 404);
        }

        // Get enrollments
        $enrollments = Enrollment::with('lesson')
            ->where('student_id', $child->id)
            ->orderBy('created_at', 'desc')
            ->get();

        // Get recent attendances
        $attendances = Attendance::where('student_id', $child->id)
            ->with('lesson')
            ->orderBy('lesson_date', 'desc')
            ->limit(20)
            ->get();

        // Get payments
        $payments = Payment::whereHas('enrollment', function ($q) use ($child) {
            $q->where('student_id', $child->id);
        })
            ->with('enrollment.lesson')
            ->orderBy('created_at', 'desc')
            ->get();

        // Calculate statistics
        $stats = $this->calculateChildStats($child->id);

        return response()->json([
            'child' => [
                'id' => $child->id,
                'name' => $child->name,
                'phone' => $child->phone,
                'branch' => $child->branch?->name,
            ],
            'enrollments' => $enrollments->map(fn($e) => [
                'id' => $e->id,
                'lesson' => [
                    'id' => $e->lesson->id,
                    'name' => $e->lesson->name,
                    'days_of_week' => $e->lesson->days_of_week,
                    'start_time' => $e->lesson->start_time,
                    'end_time' => $e->lesson->end_time,
                ],
                'status' => $e->status,
                'remaining_sessions' => $e->remaining_sessions,
                'expires_at' => $e->expires_at?->format('Y-m-d'),
            ]),
            'attendances' => $attendances->map(fn($a) => [
                'id' => $a->id,
                'lesson_name' => $a->lesson->name,
                'status' => $a->status,
                'date' => $a->lesson_date->format('Y-m-d'),
                'check_in_time' => $a->checked_at ? $a->checked_at->format('H:i') : null,
            ]),
            'payments' => $payments->map(fn($p) => [
                'id' => $p->id,
                'lesson' => $p->enrollment->lesson->name,
                'type' => $p->type,
                'amount' => $p->amount,
                'status' => $p->status,
                'paid_at' => $p->paid_at?->format('Y-m-d'),
            ]),
            'statistics' => $stats,
        ]);
    }

    /**
     * Get attendance history for a child
     */
    public function childAttendances(Request $request, int $childId)
    {
        $user = $request->user();
        $child = $user->children()->find($childId);

        if (!$child) {
            return response()->json([
                'message' => '자녀 정보를 찾을 수 없습니다.',
            ], 404);
        }

        $query = Attendance::where('student_id', $child->id)
            ->with('lesson');

        // Filter by month if provided
        if ($request->month) {
            $query->whereRaw('DATE_FORMAT(lesson_date, "%Y-%m") = ?', [$request->month]);
        }

        $attendances = $query->orderBy('lesson_date', 'desc')->get();

        return response()->json([
            'child' => [
                'id' => $child->id,
                'name' => $child->name,
                'phone' => $child->phone,
            ],
            'attendances' => $attendances->map(fn($a) => [
                'id' => $a->id,
                'lesson_name' => $a->lesson->name,
                'date' => $a->lesson_date->format('Y-m-d'),
                'status' => $a->status,
                'check_in_time' => $a->checked_at ? $a->checked_at->format('H:i') : null,
            ]),
        ]);
    }

    /**
     * Calculate statistics for a child
     */
    private function calculateChildStats(int $childId): array
    {
        $attendances = Attendance::where('student_id', $childId)->get();

        $total = $attendances->count();

        return [
            'total_lessons' => $total,
            'present' => $attendances->where('status', 'present')->count(),
            'late' => $attendances->where('status', 'late')->count(),
            'absent' => $attendances->where('status', 'absent')->count(),
            'excused' => $attendances->where('status', 'excused')->count(),
            'attendance_rate' => $total > 0
                ? round(($attendances->whereIn('status', ['present', 'late'])->count() / $total) * 100, 1)
                : 0,
        ];
    }

    /**
     * Get child enrollments
     */
    public function childEnrollments(Request $request, int $childId)
    {
        $user = $request->user();
        $child = $user->children()->find($childId);

        if (!$child) {
            return response()->json([
                'message' => '자녀 정보를 찾을 수 없습니다.',
            ], 404);
        }

        $enrollments = Enrollment::with('lesson')
            ->where('student_id', $child->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'child' => [
                'id' => $child->id,
                'name' => $child->name,
                'phone' => $child->phone,
            ],
            'enrollments' => $enrollments->map(fn($e) => [
                'id' => $e->id,
                'lesson_name' => $e->lesson->name,
                'lesson_description' => $e->lesson->description,
                'days' => $e->lesson->days_of_week,
                'time' => $e->lesson->start_time . ' - ' . $e->lesson->end_time,
                'price' => $e->lesson->price,
                'status' => $e->status,
            ]),
        ]);
    }
}
