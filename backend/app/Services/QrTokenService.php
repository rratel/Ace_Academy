<?php

namespace App\Services;

use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

class QrTokenService
{
    /**
     * Token validity in seconds (30 seconds for TOTP-like behavior)
     */
    private const TOKEN_VALIDITY = 30;

    /**
     * Generate a QR token for a student
     * Uses time-based token generation similar to TOTP
     */
    public function generateTokenForStudent(Student $student): array
    {
        $timeSlot = $this->getCurrentTimeSlot();
        $token = $this->createToken($student->id, $timeSlot, 'student');
        $expiresIn = self::TOKEN_VALIDITY - (time() % self::TOKEN_VALIDITY);

        // Store token in cache for validation
        $cacheKey = $this->getCacheKey($token);
        Cache::put($cacheKey, [
            'student_id' => $student->id,
            'student_name' => $student->name,
            'branch_id' => $student->branch_id,
            'time_slot' => $timeSlot,
            'type' => 'student',
        ], self::TOKEN_VALIDITY + 5); // Add 5 seconds buffer

        return [
            'token' => $token,
            'expires_in' => $expiresIn,
            'valid_until' => now()->addSeconds($expiresIn)->toIso8601String(),
        ];
    }

    /**
     * @deprecated Use generateTokenForStudent instead
     * Generate a QR token for a user (kept for backward compatibility)
     */
    public function generateToken(User $user): array
    {
        $timeSlot = $this->getCurrentTimeSlot();
        $token = $this->createToken($user->id, $timeSlot, 'user');
        $expiresIn = self::TOKEN_VALIDITY - (time() % self::TOKEN_VALIDITY);

        // Store token in cache for validation
        $cacheKey = $this->getCacheKey($token);
        Cache::put($cacheKey, [
            'user_id' => $user->id,
            'time_slot' => $timeSlot,
            'type' => 'user',
        ], self::TOKEN_VALIDITY + 5); // Add 5 seconds buffer

        return [
            'token' => $token,
            'expires_in' => $expiresIn,
            'valid_until' => now()->addSeconds($expiresIn)->toIso8601String(),
        ];
    }

    /**
     * Validate a QR token and return student/user info
     */
    public function validateToken(string $token): ?array
    {
        $cacheKey = $this->getCacheKey($token);
        $data = Cache::get($cacheKey);

        if (!$data) {
            return null;
        }

        // Verify time slot is still valid
        $currentSlot = $this->getCurrentTimeSlot();
        if ($data['time_slot'] !== $currentSlot && $data['time_slot'] !== $currentSlot - 1) {
            return null;
        }

        // Handle student tokens
        if (isset($data['type']) && $data['type'] === 'student') {
            $student = Student::with('branch')->find($data['student_id']);
            if (!$student || !$student->isActive()) {
                return null;
            }

            // Invalidate token after successful validation (one-time use)
            Cache::forget($cacheKey);

            return [
                'student_id' => $student->id,
                'student_name' => $student->name,
                'branch_id' => $student->branch_id,
                'branch_name' => $student->branch?->name,
            ];
        }

        // Legacy: Handle user tokens (for backward compatibility)
        $user = User::with('branch')->find($data['user_id']);
        if (!$user || $user->status !== 'active') {
            return null;
        }

        // Invalidate token after successful validation (one-time use)
        Cache::forget($cacheKey);

        return [
            'user_id' => $user->id,
            'user_name' => $user->name,
            'branch_id' => $user->branch_id,
            'branch_name' => $user->branch?->name,
        ];
    }

    /**
     * Get current time slot (changes every TOKEN_VALIDITY seconds)
     */
    private function getCurrentTimeSlot(): int
    {
        return (int) floor(time() / self::TOKEN_VALIDITY);
    }

    /**
     * Create a unique token for user/student and time slot
     */
    private function createToken(int $id, int $timeSlot, string $type = 'student'): string
    {
        $secret = config('app.key');
        $data = "{$type}:{$id}:{$timeSlot}:{$secret}";

        // Create a URL-safe base64 encoded hash
        $hash = hash_hmac('sha256', $data, $secret, true);
        $token = rtrim(strtr(base64_encode($hash), '+/', '-_'), '=');

        // Take first 32 characters for a shorter token
        return substr($token, 0, 32);
    }

    /**
     * Get cache key for token
     */
    private function getCacheKey(string $token): string
    {
        return "qr_token:{$token}";
    }
}
