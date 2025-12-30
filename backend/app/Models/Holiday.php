<?php

namespace App\Models;

use App\Models\Scopes\BranchScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Holiday extends Model
{
    use HasFactory;

    protected $fillable = [
        'branch_id',
        'date',
        'name',
        'affects_lessons',
        'is_recurring',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'affects_lessons' => 'array',
            'is_recurring' => 'boolean',
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

    // ===== Scopes =====

    public function scopeForDate($query, $date)
    {
        return $query->where('date', $date);
    }

    public function scopeForLesson($query, int $lessonId)
    {
        return $query->where(function ($q) use ($lessonId) {
            $q->whereNull('affects_lessons')
              ->orWhereJsonContains('affects_lessons', $lessonId);
        });
    }

    // ===== Helpers =====

    public function affectsAllLessons(): bool
    {
        return $this->affects_lessons === null;
    }

    public function affectsLesson(int $lessonId): bool
    {
        if ($this->affectsAllLessons()) {
            return true;
        }

        return in_array($lessonId, $this->affects_lessons ?? []);
    }
}
