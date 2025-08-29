<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\PointAssignmentController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\UserController;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

// CSRF token refresh endpoint
Route::get('/refresh-csrf', function () {
    return response()->json(['token' => csrf_token()]);
})->name('csrf.refresh');

Route::get('dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

// Add supervisor dashboard route
Route::get('supervisor-dashboard', function () {
    return Inertia::render('SupervisorDashboard');
})->middleware(['auth', 'verified'])->name('supervisor.dashboard');

// Add admin dashboard route
Route::get('admin-dashboard', function () {
    return Inertia::render('AdminDashboard');
})->middleware(['auth', 'verified'])->name('admin.dashboard');

// Add leaderboard route
Route::get('leaderboard', [App\Http\Controllers\LeaderboardController::class, 'show'])->middleware(['auth', 'verified'])->name('leaderboard');

// Add points history route
Route::get('points-history', function () {
    return Inertia::render('PointsHistory');
})->middleware(['auth', 'verified'])->name('points-history');

// Add user management route (admin only)
Route::get('user-management', function () {
    return Inertia::render('UserManagement');
})->middleware(['auth', 'verified'])->name('user-management');

// Point Assignment API Routes (prefixed with /api)
Route::middleware(['auth', 'verified'])->prefix('api')->group(function () {
    
    // Basic point assignment operations
    Route::post('/point-assignments', [PointAssignmentController::class, 'store'])->name('point-assignments.store');
    Route::get('/point-assignments/pending', [PointAssignmentController::class, 'getPendingAssignments'])->name('point-assignments.pending');
    Route::get('/point-assignments/my', [PointAssignmentController::class, 'myAssignments'])->name('point-assignments.my');
    Route::get('/point-assignments/received', [PointAssignmentController::class, 'getReceivedAssignments'])->name('point-assignments.received');
    Route::get('/point-assignments/logs', [PointAssignmentController::class, 'getPointLogs'])->name('point-assignments.logs');
    Route::get('/point-assignments/stats', [PointAssignmentController::class, 'getStats'])->name('point-assignments.stats');
    Route::get('/point-assignments/points', [PointAssignmentController::class, 'getPoints'])->name('point-assignments.points');
    Route::get('/point-assignments/pending-detailed', [PointAssignmentController::class, 'getPendingAssignmentsDetailed'])->name('point-assignments.pending-detailed');
    
    // Supervisor/Admin actions for point assignments
    Route::patch('/point-assignments/{assignment}/approve', [PointAssignmentController::class, 'approve'])->name('point-assignments.approve');
    Route::patch('/point-assignments/{assignment}/reject', [PointAssignmentController::class, 'reject'])->name('point-assignments.reject');
    Route::post('/point-assignments/bulk', [PointAssignmentController::class, 'bulkAssign'])->name('point-assignments.bulk');
    
    // Supervisor stats
    Route::get('/supervisor/stats', [PointAssignmentController::class, 'getSupervisorStats'])->name('supervisor.stats');
    
    // User management routes
    Route::get('/users', [UserController::class, 'getUsers'])->name('users.index');
    Route::get('/users/current', [UserController::class, 'getUser'])->name('users.current');
    Route::get('/users/search', [UserController::class, 'searchUsers'])->name('users.search');
    Route::get('/users/by-role', [UserController::class, 'getUsersByRole'])->name('users.by-role');
    Route::get('/users/top-performers', [UserController::class, 'getTopPerformers'])->name('users.top-performers');
    Route::get('/users/{user}/stats', [UserController::class, 'getUserStats'])->name('users.stats');
    Route::get('/users/{user}/performance', [UserController::class, 'getUserPerformance'])->name('users.performance');
    
    // Admin routes (role checking done in controllers)
    // Admin user management
    Route::get('/admin/users', [AdminController::class, 'getUsers'])->name('admin.users');
    Route::post('/admin/users', [AdminController::class, 'createUser'])->name('admin.users.create');
    Route::patch('/admin/users/{user}/role', [AdminController::class, 'updateUserRole'])->name('admin.users.update-role');
    Route::patch('/admin/users/{user}/verify', [AdminController::class, 'verifyUser'])->name('admin.users.verify');
    Route::delete('/admin/users/{user}', [AdminController::class, 'deleteUser'])->name('admin.users.delete');
    
    // Admin statistics and reports
    Route::get('/admin/stats', [AdminController::class, 'getStats'])->name('admin.stats');
    Route::get('/admin/reports', [AdminController::class, 'getReports'])->name('admin.reports');
    Route::get('/admin/system-overview', [AdminController::class, 'getSystemOverview'])->name('admin.system-overview');
    
    // Admin bulk operations
    Route::post('/admin/bulk-approve-points', [AdminController::class, 'bulkApprovePoints'])->name('admin.bulk-approve-points');
    Route::post('/admin/bulk-reject-points', [AdminController::class, 'bulkRejectPoints'])->name('admin.bulk-reject-points');
    
    // Admin audit and activity
    Route::get('/admin/audit-logs', [AdminController::class, 'getAuditLogs'])->name('admin.audit-logs');
    Route::get('/admin/users/{user}/activity', [AdminController::class, 'getUserActivity'])->name('admin.users.activity');
    
    // Leaderboard API
    Route::get('/leaderboard', [App\Http\Controllers\LeaderboardController::class, 'index'])->name('api.leaderboard');
});

// Debug route for authentication testing (remove in production)
Route::get('/debug/auth', function () {
    return response()->json([
        'authenticated' => auth()->check(),
        'user' => auth()->user()?->only(['id', 'name', 'email', 'role']),
        'session_id' => session()->getId(),
        'csrf_token' => csrf_token()
    ]);
})->name('debug.auth');

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';