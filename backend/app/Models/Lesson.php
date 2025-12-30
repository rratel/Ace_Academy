<?php

namespace App\Models;

use App\Models\Scopes\BranchScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lesson extends Model
{
    use HasFactory;

    protected $fillable = [
        'branch_id',
        'title',
        'description',
        'price',
        'days',
        'start_time',
        'end_time',
        'capacity',
        'total_sessions',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'days' => 'array',
            'price' => 'integer',
            'capacity' => 'integer',
            'total_sessions' => 'integer',
            'is_active' => 'boolean',
            'start_time' => 'datetime:H:i',
            'end_time' => 'datetime:H:i',
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

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    // ===== Scopes =====

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // ===== Accessors =====

    /**
     * Alias for 'title' column for backward compatibility
     */
    public function getNameAttribute(): ?string
    {
        return $this->title;
    }

    /**
     * Alias for 'days' column for backward compatibility
     */
    public function getDaysOfWeekAttribute(): ?array
    {
        return $this->days;
    }

    /**
     * Alias for 'capacity' column for backward compatibility
     */
    public function getMaxStudentsAttribute(): ?int
    {
        return $this->capacity;
    }

    // ===== Helpers =====

    public function getCurrentEnrollmentCount(): int
    {
        return $this->enrollments()
            ->whereIn('status', ['approved', 'pending'])
            ->count();
    }

    public function hasAvailableSlots(): bool
    {
        return $this->getCurrentEnrollmentCount() < $this->capacity;
    }

    public function getWaitlistCount(): int
    {
        return $this->enrollments()
            ->where('status', 'waitlisted')
            ->count();
    }
}
