<?php

namespace App\Models;

use App\Models\Scopes\BranchScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Enrollment extends Model
{
    use HasFactory;

    protected $fillable = [
        'branch_id',
        'student_id',
        'lesson_id',
        'status',
        'enrolled_at',
        'expires_at',
        'remaining_sessions',
        'waitlist_position',
    ];

    protected function casts(): array
    {
        return [
            'enrolled_at' => 'datetime',
            'expires_at' => 'datetime',
            'remaining_sessions' => 'integer',
            'waitlist_position' => 'integer',
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

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    // ===== Scopes =====

    public function scopeActive($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopeWaitlisted($query)
    {
        return $query->where('status', 'waitlisted')
            ->orderBy('waitlist_position');
    }

    // ===== Helpers =====

    public function isActive(): bool
    {
        return $this->status === 'approved';
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function getAttendanceCount(): int
    {
        return $this->attendances()
            ->whereIn('status', ['present', 'late', 'excused'])
            ->count();
    }
}
