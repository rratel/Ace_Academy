<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\QrController;
use App\Http\Controllers\Api\StudentAuthController;
use App\Http\Controllers\Api\StudentController;
use App\Http\Controllers\Api\ParentController;
use App\Http\Controllers\Api\AdminController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Public routes
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
});

// Student auth routes (no login required - simple name + phone verification)
Route::prefix('student')->group(function () {
    // Auth
    Route::post('/verify', [StudentAuthController::class, 'verify']);
    Route::get('/me', [StudentAuthController::class, 'me']);
    Route::get('/qr-token', [StudentAuthController::class, 'generateQrToken']);
    Route::post('/logout', [StudentAuthController::class, 'logout']);

    // Dashboard & Data (requires X-Student-Token header)
    Route::get('/dashboard', [StudentController::class, 'dashboard']);
    Route::get('/enrollments', [StudentController::class, 'enrollments']);
    Route::get('/attendances', [StudentController::class, 'attendances']);
    Route::get('/payments', [StudentController::class, 'payments']);
    Route::get('/lessons/available', [StudentController::class, 'availableLessons']);
    Route::post('/enrollments/request', [StudentController::class, 'requestEnrollment']);
});

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    // Auth
    Route::prefix('auth')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/refresh', [AuthController::class, 'refresh']);
    });

    // QR validation (for admin/reader devices)
    Route::prefix('qr')->group(function () {
        Route::post('/validate', [QrController::class, 'validateToken']);
    });

    // Parent routes
    Route::prefix('parent')->middleware('role:parent')->group(function () {
        Route::get('/dashboard', [ParentController::class, 'dashboard']);
        Route::get('/children/{childId}', [ParentController::class, 'childDetail']);
        Route::get('/children/{childId}/attendances', [ParentController::class, 'childAttendances']);
    });

    // Admin routes
    Route::prefix('admin')->middleware('role:super_admin,branch_admin')->group(function () {
        Route::get('/dashboard', [AdminController::class, 'dashboard']);

        // User management
        Route::get('/users', [AdminController::class, 'users']);
        Route::patch('/users/{id}/approve', [AdminController::class, 'approveUser']);
        Route::patch('/users/{id}/status', [AdminController::class, 'updateUserStatus']);

        // Lesson management
        Route::get('/lessons', [AdminController::class, 'lessons']);
        Route::post('/lessons', [AdminController::class, 'createLesson']);
        Route::put('/lessons/{id}', [AdminController::class, 'updateLesson']);
        Route::delete('/lessons/{id}', [AdminController::class, 'deleteLesson']);

        // Student management
        Route::get('/students', [AdminController::class, 'students']);
        Route::post('/students', [AdminController::class, 'createStudent']);
        Route::put('/students/{id}', [AdminController::class, 'updateStudent']);
        Route::delete('/students/{id}', [AdminController::class, 'deleteStudent']);

        // Attendance management
        Route::get('/attendances', [AdminController::class, 'attendances']);
        Route::patch('/attendances/{id}', [AdminController::class, 'updateAttendance']);
        Route::post('/attendances/bulk', [AdminController::class, 'bulkAttendance']);

        // Enrollment management
        Route::get('/enrollments', [AdminController::class, 'enrollments']);
        Route::post('/enrollments', [AdminController::class, 'createEnrollment']);
        Route::patch('/enrollments/{id}/approve', [AdminController::class, 'approveEnrollment']);
        Route::patch('/enrollments/{id}/reject', [AdminController::class, 'rejectEnrollment']);

        // Payment management
        Route::get('/payments', [AdminController::class, 'payments']);
        Route::post('/payments', [AdminController::class, 'createPayment']);

        // Refund management
        Route::get('/refunds', [AdminController::class, 'refunds']);
        Route::post('/refunds/calculate', [AdminController::class, 'calculateRefund']);
        Route::post('/refunds/{paymentId}', [AdminController::class, 'processRefund']);

        // Branch management (Super Admin only)
        Route::middleware('role:super_admin')->group(function () {
            Route::get('/branches', [AdminController::class, 'branches']);
            Route::post('/branches', [AdminController::class, 'createBranch']);
            Route::put('/branches/{id}', [AdminController::class, 'updateBranch']);
        });
    });
});
