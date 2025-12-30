<?php

namespace App\Models;

use App\Models\Scopes\BranchScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendance extends Model
{
    use HasFactory;

    protected $fillable = [
        'branch_id',
        'student_id',
        'lesson_id',
        'enrollment_id',
        'lesson_date',
        'checked_at',
        'status',
        'late_minutes',
        'note',
        'modified_by',
    ];

    protected function casts(): array
    {
        return [
            'lesson_date' => 'date',
            'checked_at' => 'datetime',
            'late_minutes' => 'integer',
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

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function modifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'modified_by');
    }

    // ===== Scopes =====

    public function scopePresent($query)
    {
        return $query->whereIn('status', ['present', 'late']);
    }

    public function scopeAbsent($query)
    {
        return $query->where('status', 'absent');
    }

    public function scopeForDate($query, $date)
    {
        return $query->where('lesson_date', $date);
    }

    // ===== Helpers =====

    public function isPresent(): bool
    {
        return in_array($this->status, ['present', 'late', 'excused']);
    }

    public function isLate(): bool
    {
        return $this->status === 'late';
    }

    public function isAbsent(): bool
    {
        return $this->status === 'absent';
    }

    public function isExcused(): bool
    {
        return $this->status === 'excused';
    }
}
