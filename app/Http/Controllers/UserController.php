<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\PointAssignment;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class UserController extends Controller
{
    /**
     * Get all users (existing method - enhanced)
     */
    public function getUsers()
    {
        try {
            $currentUser = Auth::user();
            
            // If admin, show all users with more details
            if ($currentUser && $currentUser->role === 'admin') {
                $users = User::select('id', 'name', 'email', 'role', 'total_verified_points')
                            ->orderBy('name')
                            ->get();
            } else {
                // For regular users and supervisors, show users for point assignment
                $users = User::where('id', '!=', $currentUser->id ?? 0)
                            ->where('role', '!=', 'admin') // Regular users can't assign points to admins
                            ->select('id', 'name', 'email', 'role')
                            ->orderBy('name')
                            ->get();
            }
            
            return response()->json($users);
        } catch (\Exception $e) {
            Log::error('Error in getUsers: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to load users'], 500);
        }
    }
    
    /**
     * Get current authenticated user (existing method - enhanced)
     */
    public function getUser()
    {
        try {
            $user = Auth::user();
            
            // Return enhanced user data including email preferences
            return response()->json([
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'total_verified_points' => $user->total_verified_points ?? 0,
                'email_on_point_received' => $user->email_on_point_received ?? true,
                'email_on_point_verified' => $user->email_on_point_verified ?? true,
                'email_on_pending_points' => $user->email_on_pending_points ?? true,
            ]);
        } catch (\Exception $e) {
            Log::error('Error in getUser: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to load user'], 500);
        }
    }

    /**
     * Get user performance summary (NEW)
     */
    public function getUserPerformance(User $user)
    {
        try {
            // Check permissions
            $currentUser = Auth::user();
            if ($currentUser->role !== 'admin' && 
                $currentUser->role !== 'supervisor' && 
                $currentUser->id !== $user->id) {
                return response()->json(['error' => 'Access denied'], 403);
            }

            $performance = [
                'user' => $user->only(['id', 'name', 'email', 'role', 'total_verified_points']),
                'points_received' => PointAssignment::where('recipient_id', $user->id)
                                                   ->where('status', 'verified')
                                                   ->sum('points'),
                'points_given' => PointAssignment::where('assignor_id', $user->id)
                                                ->where('status', 'verified')
                                                ->sum('points'),
                'pending_points' => PointAssignment::where('recipient_id', $user->id)
                                                  ->where('status', 'pending')
                                                  ->sum('points'),
                'total_assignments_received' => PointAssignment::where('recipient_id', $user->id)->count(),
                'total_assignments_given' => PointAssignment::where('assignor_id', $user->id)->count(),
                'recent_assignments' => PointAssignment::where(function ($query) use ($user) {
                                                          $query->where('recipient_id', $user->id)
                                                                ->orWhere('assignor_id', $user->id);
                                                      })
                                                      ->with(['assignor:id,name', 'recipient:id,name'])
                                                      ->latest()
                                                      ->take(10)
                                                      ->get()
            ];

            return response()->json($performance);
        } catch (\Exception $e) {
            Log::error('Error in getUserPerformance: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to load user performance'], 500);
        }
    }

    /**
     * Search users (NEW - for admin/supervisor dashboards)
     */
    public function searchUsers(Request $request)
    {
        try {
            $request->validate([
                'query' => 'required|string|min:2'
            ]);

            $currentUser = Auth::user();
            
            // Check permissions
            if (!in_array($currentUser->role, ['admin', 'supervisor'])) {
                return response()->json(['error' => 'Access denied'], 403);
            }

            $query = $request->input('query');

            $users = User::where(function ($q) use ($query) {
                            $q->where('name', 'LIKE', '%' . $query . '%')
                              ->orWhere('email', 'LIKE', '%' . $query . '%');
                        })
                        ->where('id', '!=', $currentUser->id)
                        ->select('id', 'name', 'email', 'role', 'total_verified_points')
                        ->limit(20)
                        ->get();

            return response()->json($users);
        } catch (\Exception $e) {
            Log::error('Error in searchUsers: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to search users'], 500);
        }
    }

    /**
     * Get users by role (NEW - for admin dashboard)
     */
    public function getUsersByRole(Request $request)
    {
        try {
            $currentUser = Auth::user();
            
            // Check if user is admin
            if ($currentUser->role !== 'admin') {
                return response()->json(['error' => 'Access denied'], 403);
            }

            $request->validate([
                'role' => 'sometimes|in:user,supervisor,admin'
            ]);

            $query = User::select('id', 'name', 'email', 'role', 'total_verified_points')
                        ->orderBy('name');

            if ($request->has('role')) {
                $query->where('role', $request->role);
            }

            $users = $query->get();

            // Group users by role for admin dashboard
            $usersByRole = [
                'admins' => $users->where('role', 'admin')->values(),
                'supervisors' => $users->where('role', 'supervisor')->values(),
                'users' => $users->where('role', 'user')->values(),
                'total_count' => $users->count()
            ];

            return response()->json($usersByRole);
        } catch (\Exception $e) {
            Log::error('Error in getUsersByRole: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to load users by role'], 500);
        }
    }

    /**
     * Get user statistics (NEW - for dashboards)
     */
    public function getUserStats(User $user = null)
    {
        try {
            $currentUser = Auth::user();
            $targetUser = $user ?? $currentUser;

            // Check permissions
            if ($currentUser->role !== 'admin' && 
                $currentUser->role !== 'supervisor' && 
                $currentUser->id !== $targetUser->id) {
                return response()->json(['error' => 'Access denied'], 403);
            }

            $stats = [
                'user_id' => $targetUser->id,
                'total_verified_points' => $targetUser->total_verified_points ?? 0,
                'points_given' => PointAssignment::where('assignor_id', $targetUser->id)
                                                ->where('status', 'verified')
                                                ->sum('points'),
                'points_received' => PointAssignment::where('recipient_id', $targetUser->id)
                                                   ->where('status', 'verified')
                                                   ->sum('points'),
                'pending_points' => PointAssignment::where('recipient_id', $targetUser->id)
                                                  ->where('status', 'pending')
                                                  ->sum('points'),
                'assignments_given_count' => PointAssignment::where('assignor_id', $targetUser->id)->count(),
                'assignments_received_count' => PointAssignment::where('recipient_id', $targetUser->id)->count(),
                'positive_points_received' => PointAssignment::where('recipient_id', $targetUser->id)
                                                            ->where('status', 'verified')
                                                            ->where('points', '>', 0)
                                                            ->sum('points'),
                'negative_points_received' => PointAssignment::where('recipient_id', $targetUser->id)
                                                            ->where('status', 'verified')
                                                            ->where('points', '<', 0)
                                                            ->sum('points'),
            ];

            return response()->json($stats);
        } catch (\Exception $e) {
            Log::error('Error in getUserStats: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to load user statistics'], 500);
        }
    }

    /**
     * Get top performers (NEW - for admin reports)
     */
    public function getTopPerformers(Request $request)
    {
        try {
            $currentUser = Auth::user();
            
            // Check if user is admin or supervisor
            if (!in_array($currentUser->role, ['admin', 'supervisor'])) {
                return response()->json(['error' => 'Access denied'], 403);
            }

            $limit = $request->input('limit', 10);
            $period = $request->input('period', 'all'); // all, week, month, quarter

            $query = User::select('id', 'name', 'email', 'role', 'total_verified_points')
                        ->where('role', '!=', 'admin'); // Exclude admins from leaderboard

            // Add time-based filtering if needed
            if ($period !== 'all') {
                $dateColumn = 'created_at';
                switch ($period) {
                    case 'week':
                        $date = now()->subWeek();
                        break;
                    case 'month':
                        $date = now()->subMonth();
                        break;
                    case 'quarter':
                        $date = now()->subQuarter();
                        break;
                    default:
                        $date = null;
                }

                if ($date) {
                    $query->whereHas('pointsRecieved', function ($q) use ($date) {
                        $q->where('status', 'verified')->where('created_at', '>=', $date);
                    });
                }
            }

            $topPerformers = $query->orderBy('total_verified_points', 'desc')
                                  ->limit($limit)
                                  ->get();

            return response()->json([
                'top_performers' => $topPerformers,
                'period' => $period,
                'limit' => $limit
            ]);
        } catch (\Exception $e) {
            Log::error('Error in getTopPerformers: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to load top performers'], 500);
        }
    }
}