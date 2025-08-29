<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\PointAssignment;
use App\Models\AuditLog;
use App\Events\PointVerified;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class AdminController extends Controller
{
    /**
     * Get all users for admin management
     */
    public function getUsers()
    {
        try {
            // Check if user is admin
            if (Auth::user()->role !== 'admin') {
                return response()->json(['error' => 'Access denied'], 403);
            }

            $users = User::select('id', 'name', 'email', 'role', 'total_verified_points', 'email_verified_at', 'created_at')
                        ->orderBy('name')
                        ->get();

            return response()->json($users);
        } catch (\Exception $e) {
            Log::error('Error in AdminController@getUsers: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to load users'], 500);
        }
    }

    /**
     * Create a new user (admin only)
     */
    public function createUser(Request $request)
    {
        try {
            // Check if user is admin
            if (Auth::user()->role !== 'admin') {
                return response()->json(['error' => 'Access denied'], 403);
            }

            $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|string|email|max:255|unique:users',
                'password' => 'required|string|min:8',
                'role' => 'required|in:user,supervisor,admin'
            ]);

            // Create the user
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => bcrypt($request->password),
                'role' => $request->role,
                'email_verified_at' => now(), // Auto-verify admin-created users
                'total_verified_points' => 0
            ]);

            // Log the user creation
            AuditLog::create([
                'user_id' => Auth::id(),
                'action' => 'user_created',
                'data' => [
                    'created_user_id' => $user->id,
                    'created_user_name' => $user->name,
                    'created_user_email' => $user->email,
                    'created_user_role' => $user->role
                ]
            ]);

            return response()->json([
                'message' => 'User created successfully',
                'user' => $user->only(['id', 'name', 'email', 'role', 'email_verified_at'])
            ], 201);
        } catch (\Exception $e) {
            Log::error('Error in AdminController@createUser: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to create user'], 500);
        }
    }

    /**
     * Update user role (promote/demote)
     */
    public function updateUserRole(Request $request, User $user)
    {
        try {
            // Check if user is admin
            if (Auth::user()->role !== 'admin') {
                return response()->json(['error' => 'Access denied'], 403);
            }

            $request->validate([
                'role' => 'required|in:user,supervisor,admin'
            ]);

            // Prevent admin from demoting themselves
            if ($user->id === Auth::id() && $request->role !== 'admin') {
                return response()->json(['error' => 'Cannot change your own admin role'], 400);
            }

            $oldRole = $user->role;
            $user->update(['role' => $request->role]);

            // Log the role change
            AuditLog::create([
                'user_id' => Auth::id(),
                'action' => 'role_changed',
                'data' => [
                    'target_user_id' => $user->id,
                    'target_user_name' => $user->name,
                    'old_role' => $oldRole,
                    'new_role' => $request->role
                ]
            ]);

            return response()->json([
                'message' => 'User role updated successfully',
                'user' => $user->only(['id', 'name', 'email', 'role'])
            ]);
        } catch (\Exception $e) {
            Log::error('Error in AdminController@updateUserRole: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to update user role'], 500);
        }
    }

    /**
     * NEW: Verify user email
     */
    public function verifyUser(User $user)
    {
        try {
            // Check if user is admin
            if (Auth::user()->role !== 'admin') {
                return response()->json(['error' => 'Access denied'], 403);
            }

            // Check if user is already verified
            if ($user->email_verified_at) {
                return response()->json(['error' => 'User is already verified'], 400);
            }

            // Verify the user
            $user->update([
                'email_verified_at' => now()
            ]);

            // Log the verification
            AuditLog::create([
                'user_id' => Auth::id(),
                'action' => 'user_verified',
                'data' => [
                    'target_user_id' => $user->id,
                    'target_user_name' => $user->name,
                    'target_user_email' => $user->email
                ]
            ]);

            return response()->json([
                'message' => 'User verified successfully',
                'user' => $user->only(['id', 'name', 'email', 'email_verified_at'])
            ]);
        } catch (\Exception $e) {
            Log::error('Error in AdminController@verifyUser: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to verify user'], 500);
        }
    }

    /**
     * NEW: Delete user (with safety checks)
     */
    public function deleteUser(User $user)
    {
        try {
            // Check if user is admin
            if (Auth::user()->role !== 'admin') {
                return response()->json(['error' => 'Access denied'], 403);
            }

            // Prevent admin from deleting themselves
            if ($user->id === Auth::id()) {
                return response()->json(['error' => 'Cannot delete your own account'], 400);
            }

            // Prevent deletion of the last admin
            if ($user->role === 'admin') {
                $adminCount = User::where('role', 'admin')->count();
                if ($adminCount <= 1) {
                    return response()->json(['error' => 'Cannot delete the last admin user'], 400);
                }
            }

            DB::transaction(function () use ($user) {
                // Store user data for audit log
                $userData = [
                    'deleted_user_id' => $user->id,
                    'deleted_user_name' => $user->name,
                    'deleted_user_email' => $user->email,
                    'deleted_user_role' => $user->role,
                    'total_points' => $user->total_verified_points ?? 0
                ];

                // Delete related point assignments (or you could choose to anonymize them)
                PointAssignment::where('assignor_id', $user->id)
                              ->orWhere('recipient_id', $user->id)
                              ->delete();

                // Delete the user
                $user->delete();

                // Log the deletion
                AuditLog::create([
                    'user_id' => Auth::id(),
                    'action' => 'user_deleted',
                    'data' => $userData
                ]);
            });

            return response()->json([
                'message' => 'User deleted successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Error in AdminController@deleteUser: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to delete user'], 500);
        }
    }

    /**
     * Get admin dashboard statistics
     */
    public function getStats()
    {
        try {
            // Check if user is admin
            if (Auth::user()->role !== 'admin') {
                return response()->json(['error' => 'Access denied'], 403);
            }

            $stats = [
                'totalUsers' => User::count(),
                'totalSupervisors' => User::where('role', 'supervisor')->count(),
                'totalPendingPoints' => PointAssignment::where('status', 'pending')->count(),
                'totalPointsAssigned' => PointAssignment::where('status', 'verified')->sum('points'),
                'totalPositivePoints' => PointAssignment::where('status', 'verified')->where('points', '>', 0)->sum('points'),
                'totalNegativePoints' => PointAssignment::where('status', 'verified')->where('points', '<', 0)->sum('points'),
                'verifiedUsers' => User::whereNotNull('email_verified_at')->count(),
                'unverifiedUsers' => User::whereNull('email_verified_at')->count(),
                'verifiedPoints' => PointAssignment::where('status', 'verified')->sum('points'),
                'unverifiedPoints' => PointAssignment::where('status', 'pending')->sum('points'),
                'recentActivity' => PointAssignment::with(['assignor:id,name', 'recipient:id,name'])
                                                  ->latest()
                                                  ->take(10)
                                                  ->get()
            ];

            return response()->json($stats);
        } catch (\Exception $e) {
            Log::error('Error in AdminController@getStats: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to load statistics'], 500);
        }
    }

    /**
     * Get comprehensive reports for admin
     */
    public function getReports()
    {
        try {
            // Check if user is admin
            if (Auth::user()->role !== 'admin') {
                return response()->json(['error' => 'Access denied'], 403);
            }

            $users = User::select('id', 'name', 'email', 'role', 'total_verified_points', 'email_verified_at', 'created_at')
                        ->withCount([
                            'pointsGiven',
                            'pointsRecieved',
                            'verifiedPoints',
                            'pendingPoints'
                        ])
                        ->orderBy('total_verified_points', 'desc')
                        ->get();

            return response()->json($users);
        } catch (\Exception $e) {
            Log::error('Error in AdminController@getReports: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to load reports'], 500);
        }
    }

    /**
     * Bulk approve all pending points (admin only)
     */
    public function bulkApprovePoints()
    {
        try {
            // Check if user is admin
            if (Auth::user()->role !== 'admin') {
                return response()->json(['error' => 'Access denied'], 403);
            }

            $pendingAssignments = PointAssignment::where('status', 'pending')->get();
            $approvedCount = 0;

            foreach ($pendingAssignments as $assignment) {
                $previousStatus = $assignment->status;
                
                $assignment->update([
                    'status' => 'verified',
                    'verified_by' => Auth::id(),
                    'verified_at' => now()
                ]);

                // Update recipient's total
                $assignment->recipient->increment('total_verified_points', $assignment->points);
                
                // 🔥 TRIGGER EMAIL EVENT for verification
                event(new PointVerified($assignment->load(['assignor', 'recipient', 'verifier']), $previousStatus));
                
                $approvedCount++;
            }

            // Log bulk approval
            AuditLog::create([
                'user_id' => Auth::id(),
                'action' => 'bulk_approved_points',
                'data' => ['approved_count' => $approvedCount]
            ]);

            return response()->json([
                'message' => "Successfully approved {$approvedCount} point assignments",
                'approved_count' => $approvedCount
            ]);
        } catch (\Exception $e) {
            Log::error('Error in AdminController@bulkApprovePoints: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to bulk approve points'], 500);
        }
    }

    /**
     * Get system overview for admin dashboard
     */
    public function getSystemOverview()
    {
        try {
            // Check if user is admin
            if (Auth::user()->role !== 'admin') {
                return response()->json(['error' => 'Access denied'], 403);
            }

            $overview = [
                'users_by_role' => [
                    'admins' => User::where('role', 'admin')->count(),
                    'supervisors' => User::where('role', 'supervisor')->count(),
                    'users' => User::where('role', 'user')->count()
                ],
                'verification_status' => [
                    'verified' => User::whereNotNull('email_verified_at')->count(),
                    'unverified' => User::whereNull('email_verified_at')->count()
                ],
                'points_summary' => [
                    'total_verified' => PointAssignment::where('status', 'verified')->sum('points'),
                    'total_pending' => PointAssignment::where('status', 'pending')->sum('points'),
                    'total_rejected' => PointAssignment::where('status', 'rejected')->count(),
                    'assignments_this_week' => PointAssignment::where('created_at', '>=', now()->subWeek())->count(),
                    'assignments_this_month' => PointAssignment::where('created_at', '>=', now()->subMonth())->count()
                ],
                'top_performers' => User::where('role', '!=', 'admin')
                                      ->orderBy('total_verified_points', 'desc')
                                      ->limit(5)
                                      ->get(['id', 'name', 'total_verified_points', 'role']),
                'recent_role_changes' => AuditLog::where('action', 'role_changed')
                                               ->latest()
                                               ->limit(5)
                                               ->get(),
                'recent_deletions' => AuditLog::where('action', 'user_deleted')
                                             ->latest()
                                             ->limit(5)
                                             ->get(),
                'urgent_assignments' => PointAssignment::where('status', 'pending')
                                                      ->where('created_at', '<', now()->subDays(3))
                                                      ->with(['assignor:id,name', 'recipient:id,name'])
                                                      ->count()
            ];

            return response()->json($overview);
        } catch (\Exception $e) {
            Log::error('Error in AdminController@getSystemOverview: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to load system overview'], 500);
        }
    }

    /**
     * Bulk reject pending points (admin only)
     */
    public function bulkRejectPoints(Request $request)
    {
        try {
            // Check if user is admin
            if (Auth::user()->role !== 'admin') {
                return response()->json(['error' => 'Access denied'], 403);
            }

            $request->validate([
                'assignment_ids' => 'required|array',
                'assignment_ids.*' => 'exists:point_assignments,id'
            ]);

            $assignments = PointAssignment::whereIn('id', $request->assignment_ids)
                                         ->where('status', 'pending')
                                         ->get();

            $rejectedCount = 0;
            foreach ($assignments as $assignment) {
                $previousStatus = $assignment->status;
                
                $assignment->update([
                    'status' => 'rejected',
                    'verified_by' => Auth::id(),
                    'verified_at' => now()
                ]);

                // 🔥 TRIGGER EMAIL EVENT for rejection
                event(new PointVerified($assignment->load(['assignor', 'recipient', 'verifier']), $previousStatus));
                
                $rejectedCount++;
            }

            // Log bulk rejection
            AuditLog::create([
                'user_id' => Auth::id(),
                'action' => 'bulk_rejected_points',
                'data' => [
                    'rejected_count' => $rejectedCount,
                    'assignment_ids' => $request->assignment_ids
                ]
            ]);

            return response()->json([
                'message' => "Successfully rejected {$rejectedCount} point assignments",
                'rejected_count' => $rejectedCount
            ]);
        } catch (\Exception $e) {
            Log::error('Error in AdminController@bulkRejectPoints: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to bulk reject points'], 500);
        }
    }

    /**
     * NEW: Get audit logs for admin review
     */
    public function getAuditLogs(Request $request)
    {
        try {
            // Check if user is admin
            if (Auth::user()->role !== 'admin') {
                return response()->json(['error' => 'Access denied'], 403);
            }

            $query = AuditLog::with('user:id,name')
                            ->orderBy('created_at', 'desc');

            // Filter by action if provided
            if ($request->has('action')) {
                $query->where('action', $request->action);
            }

            // Filter by date range if provided
            if ($request->has('from_date')) {
                $query->where('created_at', '>=', $request->from_date);
            }
            if ($request->has('to_date')) {
                $query->where('created_at', '<=', $request->to_date);
            }

            $logs = $query->paginate(50);

            return response()->json($logs);
        } catch (\Exception $e) {
            Log::error('Error in AdminController@getAuditLogs: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to load audit logs'], 500);
        }
    }

    /**
     * NEW: Get user activity summary
     */
    public function getUserActivity(User $user)
    {
        try {
            // Check if user is admin
            if (Auth::user()->role !== 'admin') {
                return response()->json(['error' => 'Access denied'], 403);
            }

            $activity = [
                'user' => $user->only(['id', 'name', 'email', 'role', 'total_verified_points', 'email_verified_at', 'created_at']),
                'points_given' => PointAssignment::where('assignor_id', $user->id)->count(),
                'points_received' => PointAssignment::where('recipient_id', $user->id)->count(),
                'pending_assignments' => PointAssignment::where('recipient_id', $user->id)->where('status', 'pending')->count(),
                'recent_assignments' => PointAssignment::where(function ($query) use ($user) {
                                                        $query->where('assignor_id', $user->id)
                                                              ->orWhere('recipient_id', $user->id);
                                                    })
                                                    ->with(['assignor:id,name', 'recipient:id,name'])
                                                    ->latest()
                                                    ->limit(10)
                                                    ->get(),
                'audit_entries' => AuditLog::where('user_id', $user->id)
                                          ->orWhere('data->target_user_id', $user->id)
                                          ->latest()
                                          ->limit(10)
                                          ->get()
            ];

            return response()->json($activity);
        } catch (\Exception $e) {
            Log::error('Error in AdminController@getUserActivity: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to load user activity'], 500);
        }
    }
}