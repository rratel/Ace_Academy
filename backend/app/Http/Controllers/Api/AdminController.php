<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Branch;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\Payment;
use App\Models\Student;
use App\Models\User;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AdminController extends Controller
{
    /**
     * Get admin dashboard statistics
     */
    public function dashboard(Request $request)
    {
        $user = $request->user();

        // Build query based on role
        $branchFilter = $user->isSuperAdmin() ? null : $user->branch_id;

        $studentsQuery = Student::query();
        $lessonsQuery = Lesson::query();
        $attendancesQuery = Attendance::where('lesson_date', today());
        $enrollmentsQuery = Enrollment::where('status', 'approved');

        if ($branchFilter) {
            $studentsQuery->where('branch_id', $branchFilter);
            $lessonsQuery->where('branch_id', $branchFilter);
            $attendancesQuery->where('branch_id', $branchFilter);
            $enrollmentsQuery->where('branch_id', $branchFilter);
        }

        $stats = [
            'total_students' => $studentsQuery->clone()->where('status', 'active')->count(),
            'pending_approvals' => User::where('status', 'pending')
                ->when($branchFilter, fn($q) => $q->where('branch_id', $branchFilter))
                ->count(),
            'active_lessons' => $lessonsQuery->where('is_active', true)->count(),
            'today_attendances' => $attendancesQuery->count(),
            'active_enrollments' => $enrollmentsQuery->count(),
        ];

        // Recent activities
        $recentAttendances = Attendance::with(['student', 'lesson'])
            ->when($branchFilter, fn($q) => $q->where('branch_id', $branchFilter))
            ->orderBy('lesson_date', 'desc')
            ->orderBy('checked_at', 'desc')
            ->limit(10)
            ->get();

        // Pending enrollments
        $pendingEnrollments = Enrollment::with(['student', 'lesson'])
            ->where('status', 'pending')
            ->when($branchFilter, fn($q) => $q->where('branch_id', $branchFilter))
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        return response()->json([
            'statistics' => $stats,
            'recent_attendances' => $recentAttendances->map(fn($a) => [
                'student_name' => $a->student?->name ?? 'Unknown',
                'lesson' => $a->lesson->name,
                'status' => $a->status,
                'time' => $a->checked_at ? $a->checked_at->format('H:i') : '-',
            ]),
            'pending_enrollments' => $pendingEnrollments->map(fn($e) => [
                'id' => $e->id,
                'student_name' => $e->student?->name ?? 'Unknown',
                'lesson' => $e->lesson->name,
                'requested_at' => $e->created_at->format('Y-m-d H:i'),
            ]),
        ]);
    }

    // ===== USER MANAGEMENT =====

    /**
     * List users with filters
     */
    public function users(Request $request)
    {
        $user = $request->user();

        // Exclude 'student' role - students are managed in separate table
        $query = User::with('branch')
            ->whereIn('role', ['parent', 'branch_admin', 'super_admin'])
            ->when($request->role, fn($q, $role) => $q->where('role', $role))
            ->when($request->status, fn($q, $status) => $q->where('status', $status))
            ->when($request->search, fn($q, $search) => $q->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            }));

        if (!$user->isSuperAdmin()) {
            $query->where('branch_id', $user->branch_id);
        }

        $users = $query->orderBy('created_at', 'desc')->paginate(20);

        return response()->json([
            'users' => collect($users->items())->map(fn($u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'phone' => $u->phone,
                'role' => $u->role,
                'status' => $u->status,
                'branch' => $u->branch?->name,
                'created_at' => $u->created_at->format('Y-m-d'),
            ]),
            'pagination' => [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'total' => $users->total(),
            ],
        ]);
    }

    /**
     * Approve user registration
     */
    public function approveUser(Request $request, int $id)
    {
        $targetUser = User::findOrFail($id);
        $oldValues = $targetUser->only(['status']);

        $targetUser->update(['status' => 'active']);

        AuditLog::log('user.approve', $targetUser, $oldValues, ['status' => 'active']);

        // TODO: Send approval notification via Kakao Alimtalk

        return response()->json([
            'message' => '사용자가 승인되었습니다.',
        ]);
    }

    /**
     * Update user status
     */
    public function updateUserStatus(Request $request, int $id)
    {
        $validated = $request->validate([
            'status' => 'required|in:active,inactive,rejected',
        ]);

        $targetUser = User::findOrFail($id);
        $oldValues = $targetUser->only(['status']);

        $targetUser->update(['status' => $validated['status']]);

        AuditLog::log('user.status_change', $targetUser, $oldValues, $validated);

        return response()->json([
            'message' => '사용자 상태가 변경되었습니다.',
        ]);
    }

    // ===== LESSON MANAGEMENT =====

    /**
     * List lessons
     */
    public function lessons(Request $request)
    {
        $user = $request->user();

        $query = Lesson::withCount(['enrollments' => fn($q) => $q->where('status', 'approved')]);

        if (!$user->isSuperAdmin()) {
            $query->where('branch_id', $user->branch_id);
        }

        $lessons = $query->orderBy('created_at', 'desc')->paginate(20);

        return response()->json([
            'lessons' => collect($lessons->items())->map(fn($l) => [
                'id' => $l->id,
                'name' => $l->title,
                'description' => $l->description,
                'days_of_week' => $l->days_of_week,
                'start_time' => $l->start_time,
                'end_time' => $l->end_time,
                'price' => $l->price,
                'max_students' => $l->capacity,
                'current_students' => $l->enrollments_count,
                'is_active' => $l->is_active,
            ]),
            'pagination' => [
                'current_page' => $lessons->currentPage(),
                'last_page' => $lessons->lastPage(),
                'total' => $lessons->total(),
            ],
        ]);
    }

    /**
     * Create a new lesson
     */
    public function createLesson(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'days' => 'required|array',
            'days.*' => 'in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'price' => 'required|integer|min:0',
            'max_students' => 'required|integer|min:1',
            'branch_id' => $user->isSuperAdmin() ? 'required|exists:branches,id' : 'nullable',
        ]);

        $lesson = Lesson::create([
            'title' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'days' => $validated['days'],
            'start_time' => $validated['start_time'],
            'end_time' => $validated['end_time'],
            'price' => $validated['price'],
            'capacity' => $validated['max_students'],
            'branch_id' => $validated['branch_id'] ?? $user->branch_id,
            'is_active' => true,
        ]);

        AuditLog::log('lesson.create', $lesson, null, $lesson->toArray());

        return response()->json([
            'message' => '수업이 생성되었습니다.',
            'lesson' => $lesson,
        ], 201);
    }

    /**
     * Update a lesson
     */
    public function updateLesson(Request $request, int $id)
    {
        $lesson = Lesson::findOrFail($id);
        $oldValues = $lesson->toArray();

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'days' => 'sometimes|array',
            'days.*' => 'in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
            'start_time' => 'sometimes|date_format:H:i',
            'end_time' => 'sometimes|date_format:H:i',
            'price' => 'sometimes|integer|min:0',
            'max_students' => 'sometimes|integer|min:1',
            'is_active' => 'sometimes|boolean',
        ]);

        // Map frontend field names to database column names
        $updateData = [];
        if (isset($validated['name'])) $updateData['title'] = $validated['name'];
        if (isset($validated['description'])) $updateData['description'] = $validated['description'];
        if (isset($validated['days'])) $updateData['days'] = $validated['days'];
        if (isset($validated['start_time'])) $updateData['start_time'] = $validated['start_time'];
        if (isset($validated['end_time'])) $updateData['end_time'] = $validated['end_time'];
        if (isset($validated['price'])) $updateData['price'] = $validated['price'];
        if (isset($validated['max_students'])) $updateData['capacity'] = $validated['max_students'];
        if (isset($validated['is_active'])) $updateData['is_active'] = $validated['is_active'];

        $lesson->update($updateData);

        AuditLog::log('lesson.update', $lesson, $oldValues, $lesson->toArray());

        return response()->json([
            'message' => '수업이 수정되었습니다.',
            'lesson' => $lesson,
        ]);
    }

    /**
     * Delete a lesson
     */
    public function deleteLesson(Request $request, int $id)
    {
        $lesson = Lesson::findOrFail($id);

        // Check for active enrollments
        $activeEnrollments = Enrollment::where('lesson_id', $id)
            ->where('status', 'approved')
            ->count();

        if ($activeEnrollments > 0) {
            return response()->json([
                'message' => '수강 중인 학생이 있어 삭제할 수 없습니다.',
            ], 400);
        }

        AuditLog::log('lesson.delete', $lesson, $lesson->toArray(), null);

        $lesson->delete();

        return response()->json([
            'message' => '수업이 삭제되었습니다.',
        ]);
    }

    // ===== ATTENDANCE MANAGEMENT =====

    /**
     * List attendances with filters
     */
    public function attendances(Request $request)
    {
        $user = $request->user();

        $query = Attendance::with(['student', 'lesson'])
            ->when($request->date, fn($q, $date) => $q->where('lesson_date', $date))
            ->when($request->lesson_id, fn($q, $lessonId) => $q->where('lesson_id', $lessonId))
            ->when($request->status, fn($q, $status) => $q->where('status', $status));

        if (!$user->isSuperAdmin()) {
            $query->where('branch_id', $user->branch_id);
        }

        $attendances = $query->orderBy('lesson_date', 'desc')->orderBy('checked_at', 'desc')->paginate(50);

        return response()->json([
            'attendances' => collect($attendances->items())->map(fn($a) => [
                'id' => $a->id,
                'student_name' => $a->student?->name ?? 'Unknown',
                'lesson' => $a->lesson->name,
                'status' => $a->status,
                'date' => $a->lesson_date->format('Y-m-d'),
                'check_in_time' => $a->checked_at ? $a->checked_at->format('H:i') : null,
                'note' => $a->note,
            ]),
            'pagination' => [
                'current_page' => $attendances->currentPage(),
                'last_page' => $attendances->lastPage(),
                'total' => $attendances->total(),
            ],
        ]);
    }

    /**
     * Update attendance (manual correction)
     */
    public function updateAttendance(Request $request, int $id)
    {
        $attendance = Attendance::findOrFail($id);
        $oldValues = $attendance->only(['status', 'notes']);

        $validated = $request->validate([
            'status' => 'sometimes|in:present,late,absent,excused,early_leave,makeup',
            'notes' => 'nullable|string|max:500',
        ]);

        $attendance->update($validated);

        AuditLog::log('attendance.manual_update', $attendance, $oldValues, $validated);

        return response()->json([
            'message' => '출결이 수정되었습니다.',
        ]);
    }

    /**
     * Bulk create/update attendances
     */
    public function bulkAttendance(Request $request)
    {
        $validated = $request->validate([
            'attendances' => 'required|array',
            'attendances.*.enrollment_id' => 'required|exists:enrollments,id',
            'attendances.*.status' => 'required|in:present,late,absent,excused',
            'attendances.*.date' => 'required|date',
        ]);

        $result = DB::transaction(function () use ($validated) {
            $created = 0;
            $updated = 0;

            foreach ($validated['attendances'] as $data) {
                $enrollment = Enrollment::find($data['enrollment_id']);

                // Validate enrollment is still active
                if (!$enrollment || $enrollment->status !== 'approved') {
                    continue;
                }

                // Validate enrollment is not expired
                if ($enrollment->expires_at && $enrollment->expires_at->lt(now())) {
                    continue;
                }

                $attendance = Attendance::updateOrCreate(
                    [
                        'enrollment_id' => $data['enrollment_id'],
                        'lesson_date' => $data['date'],
                    ],
                    [
                        'branch_id' => $enrollment->branch_id,
                        'student_id' => $enrollment->student_id,
                        'lesson_id' => $enrollment->lesson_id,
                        'status' => $data['status'],
                    ]
                );

                $attendance->wasRecentlyCreated ? $created++ : $updated++;
            }

            return ['created' => $created, 'updated' => $updated];
        });

        return response()->json([
            'message' => "출결 처리 완료: 생성 {$result['created']}건, 수정 {$result['updated']}건",
        ]);
    }

    // ===== PAYMENT MANAGEMENT =====

    /**
     * List payments
     */
    public function payments(Request $request)
    {
        $user = $request->user();
        $branchFilter = $user->isSuperAdmin() ? null : $user->branch_id;

        $query = Payment::with(['enrollment.student', 'enrollment.lesson'])
            ->when($branchFilter, fn($q) => $q->where('branch_id', $branchFilter))
            ->when($request->type, fn($q, $t) => $q->where('type', $t))
            ->when($request->status, fn($q, $s) => $q->where('status', $s))
            ->when($request->student_id, fn($q, $s) => $q->whereHas('enrollment', fn($eq) => $eq->where('student_id', $s)));

        $payments = $query->orderBy('created_at', 'desc')->paginate(20);

        return response()->json([
            'payments' => collect($payments->items())->map(fn($p) => [
                'id' => $p->id,
                'student_name' => $p->enrollment->student?->name ?? 'Unknown',
                'lesson_name' => $p->enrollment->lesson->name,
                'type' => $p->type,
                'amount' => $p->amount,
                'status' => $p->status,
                'paid_at' => $p->paid_at?->format('Y-m-d'),
                'refunded_at' => $p->refunded_at?->format('Y-m-d'),
                'notes' => $p->notes,
            ]),
            'pagination' => [
                'current_page' => $payments->currentPage(),
                'last_page' => $payments->lastPage(),
                'total' => $payments->total(),
            ],
        ]);
    }

    /**
     * Create payment (manual payment registration)
     */
    public function createPayment(Request $request)
    {
        $validated = $request->validate([
            'enrollment_id' => 'required|exists:enrollments,id',
            'type' => 'required|in:tuition,material,registration,other',
            'amount' => 'required|integer|min:0',
            'method' => 'nullable|in:cash,card,transfer,other',
            'notes' => 'nullable|string|max:500',
        ]);

        $enrollment = Enrollment::findOrFail($validated['enrollment_id']);

        $payment = Payment::create([
            'enrollment_id' => $enrollment->id,
            'branch_id' => $enrollment->branch_id,
            'type' => $validated['type'],
            'amount' => $validated['amount'],
            'method' => $validated['method'] ?? 'cash',
            'status' => 'paid',
            'paid_at' => now(),
            'notes' => $validated['notes'],
        ]);

        AuditLog::log('payment.create', $payment, null, $payment->toArray());

        return response()->json([
            'message' => '결제가 등록되었습니다.',
            'payment' => [
                'id' => $payment->id,
                'amount' => $payment->amount,
                'type' => $payment->type,
                'status' => $payment->status,
            ],
        ], 201);
    }

    // ===== REFUND MANAGEMENT =====

    /**
     * List refund candidates
     */
    public function refunds(Request $request)
    {
        $user = $request->user();

        // Find payments that are candidates for refund
        $query = Payment::with(['enrollment.student', 'enrollment.lesson'])
            ->where('type', 'tuition')
            ->where('status', 'paid')
            ->whereDoesntHave('relatedRefund');

        if (!$user->isSuperAdmin()) {
            $query->where('branch_id', $user->branch_id);
        }

        $payments = $query->orderBy('paid_at', 'desc')->paginate(20);

        return response()->json([
            'refund_candidates' => collect($payments->items())->map(function ($p) {
                $refundAmount = $p->calculateRefundAmount();

                return [
                    'payment_id' => $p->id,
                    'student_name' => $p->enrollment->student?->name ?? 'Unknown',
                    'lesson' => $p->enrollment->lesson->name,
                    'paid_amount' => $p->amount,
                    'calculated_refund' => $refundAmount,
                    'paid_at' => $p->paid_at->format('Y-m-d'),
                ];
            }),
            'pagination' => [
                'current_page' => $payments->currentPage(),
                'last_page' => $payments->lastPage(),
                'total' => $payments->total(),
            ],
        ]);
    }

    /**
     * Calculate refund for a payment
     */
    public function calculateRefund(Request $request)
    {
        $validated = $request->validate([
            'payment_id' => 'required|exists:payments,id',
        ]);

        $payment = Payment::with(['enrollment.lesson'])->findOrFail($validated['payment_id']);
        $refundAmount = $payment->calculateRefundAmount();

        $enrollment = $payment->enrollment;
        $attendances = Attendance::where('enrollment_id', $enrollment->id)
            ->whereIn('status', ['present', 'late'])
            ->count();

        return response()->json([
            'payment_id' => $payment->id,
            'student_name' => $enrollment->student?->name ?? 'Unknown',
            'lesson' => $enrollment->lesson->name,
            'paid_amount' => $payment->amount,
            'total_sessions' => $enrollment->lesson->total_sessions ?? 0,
            'attended_sessions' => $attendances,
            'calculated_refund' => $refundAmount,
            'formula' => '(수강료 / 총 수업 횟수) × 출석 횟수',
        ]);
    }

    /**
     * Process refund
     */
    public function processRefund(Request $request, int $paymentId)
    {
        $validated = $request->validate([
            'amount' => 'required|integer|min:0',
            'notes' => 'nullable|string|max:500',
        ]);

        $payment = Payment::findOrFail($paymentId);

        // Create refund record
        $refund = Payment::create([
            'enrollment_id' => $payment->enrollment_id,
            'branch_id' => $payment->branch_id,
            'user_id' => $payment->user_id,
            'type' => 'refund',
            'amount' => $validated['amount'],
            'status' => 'refunded',
            'refunded_at' => now(),
            'notes' => $validated['notes'],
            'related_payment_id' => $payment->id,
        ]);

        AuditLog::log('refund.process', $refund, null, [
            'original_payment_id' => $payment->id,
            'refund_amount' => $validated['amount'],
        ]);

        // TODO: Send refund notification via Kakao Alimtalk

        return response()->json([
            'message' => '환급 처리가 완료되었습니다.',
            'refund' => [
                'id' => $refund->id,
                'amount' => $refund->amount,
                'refunded_at' => $refund->refunded_at->format('Y-m-d'),
            ],
        ]);
    }

    // ===== BRANCH MANAGEMENT (Super Admin Only) =====

    /**
     * List branches
     */
    public function branches(Request $request)
    {
        $branches = Branch::withCount([
            'users' => fn($q) => $q->where('role', 'student')->where('status', 'active'),
            'lessons' => fn($q) => $q->where('is_active', true),
        ])->get();

        return response()->json([
            'branches' => $branches->map(fn($b) => [
                'id' => $b->id,
                'name' => $b->name,
                'code' => $b->code,
                'address' => $b->address,
                'phone' => $b->phone,
                'is_active' => $b->is_active,
                'students_count' => $b->users_count,
                'lessons_count' => $b->lessons_count,
            ]),
        ]);
    }

    /**
     * Create branch
     */
    public function createBranch(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'code' => 'required|string|max:20|unique:branches',
            'address' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:20',
        ]);

        $branch = Branch::create([
            ...$validated,
            'is_active' => true,
        ]);

        AuditLog::log('branch.create', $branch, null, $branch->toArray());

        return response()->json([
            'message' => '지점이 생성되었습니다.',
            'branch' => $branch,
        ], 201);
    }

    /**
     * Update branch
     */
    public function updateBranch(Request $request, int $id)
    {
        $branch = Branch::findOrFail($id);
        $oldValues = $branch->toArray();

        $validated = $request->validate([
            'name' => 'sometimes|string|max:100',
            'code' => 'sometimes|string|max:20|unique:branches,code,' . $id,
            'address' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:20',
            'is_active' => 'sometimes|boolean',
        ]);

        $branch->update($validated);

        AuditLog::log('branch.update', $branch, $oldValues, $branch->toArray());

        return response()->json([
            'message' => '지점이 수정되었습니다.',
            'branch' => $branch,
        ]);
    }

    // ===== ENROLLMENT MANAGEMENT =====

    /**
     * List enrollments with filters
     */
    public function enrollments(Request $request)
    {
        $user = $request->user();
        $branchFilter = $user->isSuperAdmin() ? null : $user->branch_id;

        $query = Enrollment::with(['student', 'lesson'])
            ->when($branchFilter, fn($q) => $q->where('branch_id', $branchFilter))
            ->when($request->status, fn($q, $s) => $q->where('status', $s))
            ->when($request->lesson_id, fn($q, $l) => $q->where('lesson_id', $l))
            ->when($request->student_id, fn($q, $s) => $q->where('student_id', $s));

        $enrollments = $query->orderBy('created_at', 'desc')->paginate(20);

        return response()->json([
            'enrollments' => collect($enrollments->items())->map(fn($e) => [
                'id' => $e->id,
                'student_id' => $e->student_id,
                'student_name' => $e->student?->name ?? 'Unknown',
                'lesson_id' => $e->lesson_id,
                'lesson_name' => $e->lesson->name,
                'status' => $e->status,
                'remaining_sessions' => $e->remaining_sessions,
                'expires_at' => $e->expires_at?->format('Y-m-d'),
                'created_at' => $e->created_at->format('Y-m-d'),
            ]),
            'pagination' => [
                'current_page' => $enrollments->currentPage(),
                'last_page' => $enrollments->lastPage(),
                'total' => $enrollments->total(),
            ],
        ]);
    }

    /**
     * Create enrollment (admin registers student for a lesson)
     */
    public function createEnrollment(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'student_id' => 'required|exists:students,id',
            'lesson_id' => 'required|exists:lessons,id',
            'remaining_sessions' => 'nullable|integer|min:0',
            'expires_at' => 'nullable|date|after:today',
        ]);

        $lesson = Lesson::findOrFail($validated['lesson_id']);
        $student = Student::findOrFail($validated['student_id']);

        // Check if already enrolled
        $existing = Enrollment::where('student_id', $student->id)
            ->where('lesson_id', $lesson->id)
            ->whereIn('status', ['pending', 'approved'])
            ->first();

        if ($existing) {
            return response()->json([
                'message' => '이미 등록된 수강 신청이 있습니다.',
            ], 400);
        }

        // Check lesson capacity
        $currentCount = Enrollment::where('lesson_id', $lesson->id)
            ->where('status', 'approved')
            ->count();

        if ($currentCount >= $lesson->capacity) {
            return response()->json([
                'message' => '정원이 초과되었습니다.',
            ], 400);
        }

        $enrollment = Enrollment::create([
            'student_id' => $student->id,
            'lesson_id' => $lesson->id,
            'branch_id' => $lesson->branch_id,
            'status' => 'approved',
            'remaining_sessions' => $validated['remaining_sessions'] ?? $lesson->total_sessions ?? 12,
            'expires_at' => $validated['expires_at'] ?? now()->addMonth(),
            'enrolled_at' => now(),
        ]);

        AuditLog::log('enrollment.create', $enrollment, null, $enrollment->toArray());

        return response()->json([
            'message' => '수강 등록이 완료되었습니다.',
            'enrollment' => [
                'id' => $enrollment->id,
                'student_name' => $student->name,
                'lesson_name' => $lesson->name,
                'status' => $enrollment->status,
            ],
        ], 201);
    }

    /**
     * Approve enrollment
     */
    public function approveEnrollment(Request $request, int $id)
    {
        $enrollment = Enrollment::findOrFail($id);
        $oldValues = $enrollment->only(['status']);

        $enrollment->update([
            'status' => 'approved',
            'expires_at' => now()->addMonth(),
        ]);

        AuditLog::log('enrollment.approve', $enrollment, $oldValues, ['status' => 'approved']);

        return response()->json([
            'message' => '수강 신청이 승인되었습니다.',
        ]);
    }

    /**
     * Reject enrollment
     */
    public function rejectEnrollment(Request $request, int $id)
    {
        $enrollment = Enrollment::findOrFail($id);
        $oldValues = $enrollment->only(['status']);

        $enrollment->update(['status' => 'rejected']);

        AuditLog::log('enrollment.reject', $enrollment, $oldValues, ['status' => 'rejected']);

        return response()->json([
            'message' => '수강 신청이 거절되었습니다.',
        ]);
    }

    // ===== STUDENT MANAGEMENT =====

    /**
     * List students
     */
    public function students(Request $request)
    {
        $user = $request->user();
        $branchFilter = $user->isSuperAdmin() ? null : $user->branch_id;

        $query = Student::with('branch')
            ->when($branchFilter, fn($q) => $q->where('branch_id', $branchFilter));

        if ($request->status) {
            $query->where('status', $request->status);
        }

        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%")
                    ->orWhere('phone', 'like', "%{$request->search}%");
            });
        }

        $students = $query->orderBy('created_at', 'desc')->paginate(20);

        return response()->json([
            'students' => collect($students->items())->map(fn($s) => [
                'id' => $s->id,
                'name' => $s->name,
                'phone' => $s->formatted_phone,
                'branch' => $s->branch?->name,
                'status' => $s->status,
                'created_at' => $s->created_at->format('Y-m-d'),
            ]),
            'pagination' => [
                'current_page' => $students->currentPage(),
                'last_page' => $students->lastPage(),
                'total' => $students->total(),
            ],
        ]);
    }

    /**
     * Create student
     */
    public function createStudent(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => 'required|string|max:50',
            'phone' => 'required|string|max:20|unique:students,phone',
            'birth_date' => 'nullable|date',
            'gender' => 'nullable|in:male,female',
            'school' => 'nullable|string|max:100',
            'grade' => 'nullable|integer|min:1|max:12',
            'parent_id' => 'nullable|exists:users,id',
            'notes' => 'nullable|string|max:500',
        ]);

        // Normalize phone number
        $validated['phone'] = preg_replace('/[^0-9]/', '', $validated['phone']);

        $student = Student::create([
            ...$validated,
            'branch_id' => $user->isSuperAdmin() ? ($request->branch_id ?? 1) : $user->branch_id,
            'status' => 'active',
        ]);

        AuditLog::log('student.create', $student, null, $student->toArray());

        return response()->json([
            'message' => '학생이 등록되었습니다.',
            'student' => [
                'id' => $student->id,
                'name' => $student->name,
                'phone' => $student->formatted_phone,
            ],
        ], 201);
    }

    /**
     * Update student
     */
    public function updateStudent(Request $request, int $id)
    {
        $student = Student::findOrFail($id);
        $oldValues = $student->toArray();

        $validated = $request->validate([
            'name' => 'sometimes|string|max:50',
            'phone' => 'sometimes|string|max:20|unique:students,phone,' . $id,
            'birth_date' => 'nullable|date',
            'gender' => 'nullable|in:male,female',
            'school' => 'nullable|string|max:100',
            'grade' => 'nullable|integer|min:1|max:12',
            'status' => 'sometimes|in:active,inactive,pending',
            'parent_id' => 'nullable|exists:users,id',
            'notes' => 'nullable|string|max:500',
        ]);

        if (isset($validated['phone'])) {
            $validated['phone'] = preg_replace('/[^0-9]/', '', $validated['phone']);
        }

        $student->update($validated);

        AuditLog::log('student.update', $student, $oldValues, $student->toArray());

        return response()->json([
            'message' => '학생 정보가 수정되었습니다.',
            'student' => $student,
        ]);
    }

    /**
     * Delete student
     */
    public function deleteStudent(int $id)
    {
        $student = Student::findOrFail($id);

        // Check for active enrollments
        if ($student->enrollments()->where('status', 'approved')->exists()) {
            return response()->json([
                'message' => '활성화된 수강이 있는 학생은 삭제할 수 없습니다.',
            ], 400);
        }

        AuditLog::log('student.delete', $student, $student->toArray(), null);

        $student->delete();

        return response()->json([
            'message' => '학생이 삭제되었습니다.',
        ]);
    }
}
