<?php

namespace App\Models;

use App\Models\Scopes\BranchScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'branch_id',
        'user_id',
        'enrollment_id',
        'type',
        'amount',
        'attendance_count',
        'refund_amount',
        'status',
        'pg_transaction_id',
        'pg_response',
        'paid_at',
        'refunded_at',
        'processed_by',
        'related_payment_id',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'attendance_count' => 'integer',
            'refund_amount' => 'integer',
            'pg_response' => 'array',
            'paid_at' => 'datetime',
            'refunded_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::addGlobalScope(new BranchScope());
    }

    // ===== Relationships =====

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    /**
     * Get the refund payment associated with this tuition payment
     */
    public function relatedRefund(): HasOne
    {
        return $this->hasOne(Payment::class, 'related_payment_id');
    }

    /**
     * Get the original tuition payment for this refund
     */
    public function originalPayment(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'related_payment_id');
    }

    // ===== Scopes =====

    public function scopeTuition($query)
    {
        return $query->where('type', 'tuition');
    }

    public function scopeRefund($query)
    {
        return $query->where('type', 'refund');
    }

    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }

    // ===== Helpers =====

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    public function isRefundReady(): bool
    {
        return $this->status === 'refund_ready';
    }

    /**
     * 환급액 계산
     * RefundAmount = (PaidAmount / TotalLessons) × AttendanceCount
     */
    public function calculateRefundAmount(): int
    {
        $enrollment = $this->enrollment;
        $lesson = $enrollment->lesson;

        $totalSessions = $lesson->total_sessions;
        $attendanceCount = $enrollment->getAttendanceCount();

        if ($totalSessions <= 0) {
            return 0;
        }

        return (int) floor(($this->amount / $totalSessions) * $attendanceCount);
    }
}
