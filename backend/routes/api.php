<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\ExamController;
use App\Http\Controllers\ResultController;
use App\Http\Controllers\CourseController;
use App\Http\Controllers\AdminAuthController;
use App\Http\Controllers\AdminManagementController;
use App\Http\Controllers\VoiceAssistantController;
use Illuminate\Support\Facades\Storage;

// Public routes
Route::post('/auth/forgot-password', [\App\Http\Controllers\PortalController::class, 'forgetPassword'])->middleware('throttle:auth-register');
Route::post('/auth/reset-password', [\App\Http\Controllers\PortalController::class, 'resetPassword'])->middleware('throttle:auth-login');
Route::post('/auth/register', [AuthController::class, 'register'])->middleware('throttle:auth-register');
Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:auth-login');
Route::post('/auth/otp/verify', [AuthController::class, 'verifyOtp'])->middleware('throttle:auth-otp-verify');
Route::post('/auth/otp/resend', [AuthController::class, 'resendOtp'])->middleware('throttle:auth-otp-resend');
Route::post('/auth/google-login', [AuthController::class, 'googleLogin'])->middleware('throttle:auth-google');
Route::post('/admin/auth/google-login', [AdminAuthController::class, 'googleLogin'])->middleware('throttle:auth-google');

// Serve stored public files
Route::get('/storage/{path}', function ($path) {
    if (str_contains($path, '..')) {
        abort(404);
    }

    if (str_starts_with($path, 'course-image/')) {
        $course = \App\Models\Course::find((int) str_replace('course-image/', '', $path));
        abort_unless($course?->image_data, 404);
        return response(base64_decode($course->image_data), 200, [
            'Content-Type' => $course->image_mime_type ?: 'application/octet-stream',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    $disk = Storage::disk('public');
    if (!$disk->exists($path)) {
        return response('', 404);
    }

    return response()->file($disk->path($path), [
        'Content-Type' => $disk->mimeType($path) ?: 'application/octet-stream',
        'Cache-Control' => 'no-cache, no-store, must-revalidate',
    ]);
})->where('path', '.*');

// Courses (public)
Route::get('/courses/{id}/image', [CourseController::class, 'image']);
Route::get('/courses', [CourseController::class, 'getAllCourses']);
Route::get('/courses/{id}', [CourseController::class, 'getCourseDetails']);
Route::post('/assistant/respond', [VoiceAssistantController::class, 'respond'])->middleware('throttle:30,1');
Route::get('/assistant/knowledge', [VoiceAssistantController::class, 'knowledge'])->middleware('throttle:60,1');

// Exam Questions (public but ideally protected)
Route::get('/exam/questions', [ExamController::class, 'getQuestions'])->middleware(['auth:sanctum', 'student.active']);

// Protected routes
Route::middleware(['auth:sanctum', 'student.active'])->group(function () {
    // Auth
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/profile', [AuthController::class, 'profile']);

    // Student
    Route::get('/student/status', [\App\Http\Controllers\PortalController::class, 'status']);
    Route::patch('/student/notifications/{id}/read', [\App\Http\Controllers\PortalController::class, 'readNotification']);
    Route::post('/auth/revoke-other-sessions', [\App\Http\Controllers\PortalController::class, 'revokeOthers']);
    Route::get('/student/dashboard', [StudentController::class, 'dashboard']);
    Route::get('/student/number', [StudentController::class, 'getStudentNumber']);
    Route::put('/student/profile', [StudentController::class, 'updateProfile']);

    // Exam
    Route::get('/exam/session', [\App\Http\Controllers\ExamSessionController::class, 'current']);
    Route::post('/exam/session', [\App\Http\Controllers\ExamSessionController::class, 'start']);
    Route::put('/exam/session', [\App\Http\Controllers\ExamSessionController::class, 'save']);
    Route::post('/exam/submit', [\App\Http\Controllers\ExamSessionController::class, 'submit']);
    Route::get('/exam/result', [ExamController::class, 'getExamResult']);

    // Results
    Route::get('/results/recommendation', [ResultController::class, 'getRecommendation']);
    Route::post('/results/recommendation', [ResultController::class, 'generateRecommendation'])->middleware('throttle:6,1,program-recommendation:');
});

Route::prefix('admin')->middleware(['auth:sanctum', 'admin'])->group(function () {
    Route::get('/auth/profile', [AdminAuthController::class, 'profile']);
    Route::post('/auth/logout', [AdminAuthController::class, 'logout']);
    Route::get('/dashboard', [AdminManagementController::class, 'dashboard']);
    Route::get('/students', [AdminManagementController::class, 'students']);
    Route::get('/student-logs', [AdminManagementController::class, 'studentLogs']);
    Route::get('/students/{student}', [AdminManagementController::class, 'student']);
    Route::get('/results', [AdminManagementController::class, 'results']);
    Route::get('/courses', [AdminManagementController::class, 'courses']);
    Route::get('/questions', [AdminManagementController::class, 'questions']);

    Route::middleware('admin:admissions_staff')->group(function () {
        Route::patch('/students/{student}/status', [AdminManagementController::class, 'updateStudentStatus']);
        Route::post('/courses', [AdminManagementController::class, 'storeCourse']);
        Route::put('/courses/{course}', [AdminManagementController::class, 'updateCourse']);
        Route::delete('/courses/{course}', [AdminManagementController::class, 'destroyCourse']);
        Route::post('/results/import', [\App\Http\Controllers\ResultReviewController::class, 'preview']);
        Route::post('/results/import/commit', [\App\Http\Controllers\ResultReviewController::class, 'commit']);
        Route::patch('/results/approve-batch', [\App\Http\Controllers\ResultReviewController::class, 'approveBatch']);
        Route::patch('/results/{examResult}/correct', [\App\Http\Controllers\ResultReviewController::class, 'correct']);
        Route::get('/results/import-template', [AdminManagementController::class, 'downloadResultTemplate']);
        Route::patch('/results/publish-approved', [AdminManagementController::class, 'publishApprovedResults']);
        Route::patch('/results/registrar-pass', [AdminManagementController::class, 'registrarPassResults']);
        Route::patch('/results/{examResult}/approve', [\App\Http\Controllers\ResultReviewController::class, 'approveOne']);
        Route::patch('/results/{examResult}/publish', [\App\Http\Controllers\ResultReviewController::class, 'publishOne']);
    });

    Route::middleware('admin:exam_manager')->group(function () {
        Route::post('/questions', [AdminManagementController::class, 'storeQuestion']);
        Route::put('/questions/{question}', [AdminManagementController::class, 'updateQuestion']);
        Route::delete('/questions/{question}', [AdminManagementController::class, 'destroyQuestion']);
    });

    Route::middleware('admin:super_admin')->group(function () {
        Route::get('/operations', [\App\Http\Controllers\OperationsController::class, 'health']);
        Route::post('/operations/notifications/{id}/retry', [\App\Http\Controllers\OperationsController::class, 'retry']);
        Route::get('/admins', [AdminManagementController::class, 'admins']);
        Route::post('/admins', [AdminManagementController::class, 'inviteAdmin']);
        Route::patch('/admins/{admin}', [AdminManagementController::class, 'updateAdmin']);
        Route::delete('/admins/{admin}', [AdminManagementController::class, 'destroyAdmin']);
        Route::get('/activity-logs', [AdminManagementController::class, 'activityLogs']);
    });
});
