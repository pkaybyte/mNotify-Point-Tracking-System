<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PointAssignmentController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\UserPreferencesController;

// Temporary test routes without authentication
Route::get('/users', [UserController::class, 'getUsers']);
Route::post('/point-assignments-test', [PointAssignmentController::class, 'store']);

// Authenticated routes
Route::middleware('auth:sanctum')->group(function () {
    // User routes
    Route::get('/user', [UserController::class, 'getUser']);
    // Route::get('/users', [UserController::class, 'getUsers']); // Moved above temporarily

    // Point assignment routes
    Route::get('/points', [PointAssignmentController::class, 'getPoints']);
    Route::post('/point-assignments', [PointAssignmentController::class, 'store']);
    Route::post('/point-assignments/bulk-assign', [PointAssignmentController::class, 'bulkAssign']); // NEW: Bulk assign
    Route::patch('/point-assignments/{assignment}/approve', [PointAssignmentController::class, 'approve']);
    Route::patch('/point-assignments/{assignment}/reject', [PointAssignmentController::class, 'reject']); // NEW: Reject points
    Route::get('/point-assignments/my', [PointAssignmentController::class, 'myAssignments']);
    Route::get('/point-assignments/logs', [PointAssignmentController::class, 'getPointLogs']);
    Route::get('/point-assignments/pending', [PointAssignmentController::class, 'getPendingAssignments']); // NEW: Get pending assignments
    Route::get('/point-assignments/stats', [PointAssignmentController::class, 'getStats']); // NEW: Get user statistics

    // User Email Preferences Routes (NEW)
    Route::get('/user/email-preferences', [UserPreferencesController::class, 'getEmailPreferences']);
    Route::patch('/user/email-preferences', [UserPreferencesController::class, 'updateEmailPreferences']);

    // Audit logs
    Route::get('/audit-logs', [AuditLogController::class, 'getRecentLogs']);

    // Dashboard
    Route::get('/dashboard-data', [DashboardController::class, 'getDashboardData']);

    Route::get('/point-assignments/received', [PointAssignmentController::class, 'getReceivedAssignments'])
    ->middleware('auth');

    Route::middleware('auth')->group(function () {
    // Existing routes...
    
    Route::get('/point-assignments/pending', [PointAssignmentController::class, 'getPendingAssignments']);
    Route::get('/point-assignments/my', [PointAssignmentController::class, 'myAssignments']);
    Route::get('/point-assignments/received', [PointAssignmentController::class, 'getReceivedAssignments']);
    Route::get('/supervisor/stats', [PointAssignmentController::class, 'getSupervisorStats']);
    
    Route::patch('/point-assignments/{assignment}/approve', [PointAssignmentController::class, 'approve']);
    Route::patch('/point-assignments/{assignment}/reject', [PointAssignmentController::class, 'reject']);
});
});