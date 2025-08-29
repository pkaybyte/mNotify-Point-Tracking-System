<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\PointAssignment;
use App\Models\AuditLog;
use App\Events\PointVerified;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class SupervisorController extends Controller
{
    /**
     * Get supervisor dashboard statistics
     */
    public function getStats()
    {
        try {
            // Check if user is supervisor or admin
            if (!in_array(Auth::user()->role, ['supervisor', 'admin'])) {
                return response()->json(['error' => 'Access denied'], 403);
            }

            $oneWeekAgo = Carbon::now()->subWeek();

            $stats = [
                'totalTeamMembers' => User::where('role', '!=', 'admin')->count(),
                'pendingReviews' => PointAssignment::where('status', 'pending')->count(),
                'approvedThisWeek' => PointAssignment::where('status', 'verified')
                                                    ->where('verified_at', '>=', $oneWeekAgo)
                                                    ->count(),
                'rejectedThisWeek' => PointAssignment::where('status', 'rejected')
                                                    ->where('verified_at', '>=', $oneWeekAgo)
                                                    ->count(),
                'averageTeamPoints' => round(User::where('role', '!=', 'admin')
                                               ->avg('total_verified_points') ?? 0, 1),
                'urgentAssignments' => PointAssignment::where('status', 'pending')
                                                     ->where('created_at', '<', Carbon::now()->subDays(3))
                                                     ->count(),
                'myAssignmentsThisWeek' => PointAssignment::where('assignor_id', Auth::id())
                                                         ->where('created_at', '>=', $oneWeekAgo)
                                                         ->count()
            ];

            return response()->json($stats);
        } catch (\Exception $e) {
            Log::error('Error in SupervisorController@getStats: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to load statistics'], 500);
        }
    }

    /**
     * Get supervisor's team performance data
     */
    public function getTeamPerformance()
    {
        try {
            // Check if user is supervisor or admin
            if (!in_array(Auth::user()->role, ['supervisor', 'admin'])) {
                return response()->json(['error' => 'Access denied'], 403);
            }

            $teamPerformance = User::where('role', '!=', 'admin')
                                  ->select('id', 'name', 'email', 'role', 'total_verified_points')
                                  ->withCount([
                                      'pointsRecieved as total_received',
                                      'verifiedPoints as verified_received',
                                      'pendingPoints as pending_received'
                                  ])
                                  ->orderBy('total_verified_points', 'desc')
                                  ->get();

            return response()->json($teamPerformance);
        } catch (\Exception $e) {
            Log::error('Error in SupervisorController@getTeamPerformance: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to load team performance'], 500);
        }
    }

    /**
     * Get assignments that need urgent attention (older than 3 days)
     */
    public function getUrgentAssignments()
    {
        try {
            // Check if user is supervisor or admin
            if (!in_array(Auth::user()->role, ['supervisor', 'admin'])) {
                return response()->json(['error' => 'Access denied'], 403);
            }

            $threeDaysAgo = Carbon::now()->subDays(3);

            $urgentAssignments = PointAssignment::where('status', 'pending')
                                               ->where('created_at', '<', $threeDaysAgo)
                                               ->with(['assignor:id,name', 'recipient:id,name'])
                                               ->orderBy('created_at', 'asc')
                                               ->get()
                                               ->map(function ($assignment) {
                                                   return [
                                                       'id' => $assignment->id,
                                                       'assignor' => $assignment->assignor,
                                                       'recipient' => $assignment->recipient,
                                                       'points' => $assignment->points,
                                                       'reason' => $assignment->reason,
                                                       'created_at' => $assignment->created_at->toISOString(),
                                                       'days_pending' => $assignment->created_at->diffInDays(now())
                                                   ];
                                               });

            return response()->json([
                'urgent_assignments' => $urgentAssignments,
                'count' => $urgentAssignments->count()
            ]);
        } catch (\Exception $e) {
            Log::error('Error in SupervisorController@getUrgentAssignments: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to load urgent assignments'], 500);
        }
    }

    /**
     * Bulk approve multiple assignments
     */
    public function bulkApprove(Request $request)
    {
        try {
            // Check if user is supervisor or admin
            if (!in_array(Auth::user()->role, ['supervisor', 'admin'])) {
                return response()->json(['error' => 'Access denied'], 403);
            }

            $request->validate([
                'assignment_ids' => 'required|array',
                'assignment_ids.*' => 'exists:point_assignments,id'
            ]);

            $assignments = PointAssignment::whereIn('id', $request->assignment_ids)
                                         ->where('status', 'pending')
                                         ->get();

            $approvedCount = 0;
            foreach ($assignments as $assignment) {
                $previousStatus = $assignment->status;
                
                $assignment->update([
                    'status' => 'verified',
                    'verified_by' => Auth::id(),
                    'verified_at' => now()
                ]);

                // Update recipient's total verified points
                $assignment->recipient->increment('total_verified_points', $assignment->points);
                
                // 🔥 TRIGGER EMAIL EVENT for verification
                event(new PointVerified($assignment->load(['assignor', 'recipient', 'verifier']), $previousStatus));
                
                $approvedCount++;
            }

            // Log bulk approval
            AuditLog::create([
                'user_id' => Auth::id(),
                'action' => 'bulk_approved_points',
                'data' => [
                    'approved_count' => $approvedCount,
                    'assignment_ids' => $request->assignment_ids
                ]
            ]);

            return response()->json([
                'message' => "Successfully approved {$approvedCount} point assignments",
                'approved_count' => $approvedCount
            ]);
        } catch (\Exception $e) {
            Log::error('Error in SupervisorController@bulkApprove: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to bulk approve assignments'], 500);
        }
    }

    /**
     * Get weekly point trends for supervisor dashboard
     */
    public function getWeeklyTrends()
    {
        try {
            // Check if user is supervisor or admin
            if (!in_array(Auth::user()->role, ['supervisor', 'admin'])) {
                return response()->json(['error' => 'Access denied'], 403);
            }

            $trends = [];
            for ($i = 6; $i >= 0; $i--) {
                $date = Carbon::now()->subDays($i);
                $dayAssignments = PointAssignment::whereDate('created_at', $date)
                                                ->where('status', 'verified')
                                                ->get();
                
                $trends[] = [
                    'date' => $date->format('Y-m-d'),
                    'day' => $date->format('D'),
                    'total_points' => $dayAssignments->sum('points'),
                    'assignments_count' => $dayAssignments->count(),
                    'positive_points' => $dayAssignments->where('points', '>', 0)->sum('points'),
                    'negative_points' => $dayAssignments->where('points', '<', 0)->sum('points')
                ];
            }

            return response()->json($trends);
        } catch (\Exception $e) {
            Log::error('Error in SupervisorController@getWeeklyTrends: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to load weekly trends'], 500);
        }
    }

    /**
     * Get supervisor's own assignments
     */
    public function getSupervisorAssignments()
    {
        try {
            // Check if user is supervisor or admin
            if (!in_array(Auth::user()->role, ['supervisor', 'admin'])) {
                return response()->json(['error' => 'Access denied'], 403);
            }

            $assignments = PointAssignment::where('assignor_id', Auth::id())
                                         ->with(['recipient:id,name,email'])
                                         ->orderBy('created_at', 'desc')
                                         ->get()
                                         ->map(function ($assignment) {
                                             return [
                                                 'id' => $assignment->id,
                                                 'assignor' => ['name' => 'You'],
                                                 'recipient' => $assignment->recipient,
                                                 'points' => $assignment->points,
                                                 'reason' => $assignment->reason,
                                                 'status' => $assignment->status,
                                                 'created_at' => $assignment->created_at->toISOString(),
                                                 'verified_at' => $assignment->verified_at ? $assignment->verified_at->toISOString() : null
                                             ];
                                         });

            return response()->json($assignments);
        } catch (\Exception $e) {
            Log::error('Error in SupervisorController@getSupervisorAssignments: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to load supervisor assignments'], 500);
        }
    }

    /**
     * Get pending assignments with enhanced details for supervisor review
     */
    public function getPendingAssignmentsDetailed()
    {
        try {
            // Check if user is supervisor or admin
            if (!in_array(Auth::user()->role, ['supervisor', 'admin'])) {
                return response()->json(['error' => 'Access denied'], 403);
            }

            $pendingAssignments = PointAssignment::where('status', 'pending')
                                               ->with([
                                                   'assignor:id,name,email',
                                                   'recipient:id,name,email'
                                               ])
                                               ->orderBy('created_at', 'desc')
                                               ->get()
                                               ->map(function ($assignment) {
                                                   $daysPending = $assignment->created_at->diffInDays(now());
                                                   
                                                   return [
                                                       'id' => $assignment->id,
                                                       'assignor' => $assignment->assignor,
                                                       'recipient' => $assignment->recipient,
                                                       'points' => $assignment->points,
                                                       'reason' => $assignment->reason,
                                                       'created_at' => $assignment->created_at->toISOString(),
                                                       'days_pending' => $daysPending,
                                                       'urgency' => $daysPending >= 3 ? 'urgent' : ($daysPending >= 1 ? 'attention' : 'normal'),
                                                       'is_bulk_assignment' => $assignment->is_bulk_assignment ?? false
                                                   ];
                                               });

            return response()->json([
                'pending_assignments' => $pendingAssignments,
                'count' => $pendingAssignments->count(),
                'urgent_count' => $pendingAssignments->where('urgency', 'urgent')->count(),
                'attention_count' => $pendingAssignments->where('urgency', 'attention')->count()
            ]);
        } catch (\Exception $e) {
            Log::error('Error in SupervisorController@getPendingAssignmentsDetailed: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to load pending assignments'], 500);
        }
    }

    /**
     * Get team summary for supervisor dashboard
     */
    public function getTeamSummary()
    {
        try {
            // Check if user is supervisor or admin
            if (!in_array(Auth::user()->role, ['supervisor', 'admin'])) {
                return response()->json(['error' => 'Access denied'], 403);
            }

            $teamSummary = [
                'total_members' => User::where('role', '!=', 'admin')->count(),
                'active_members_this_week' => User::where('role', '!=', 'admin')
                                                 ->whereHas('pointsRecieved', function ($query) {
                                                     $query->where('created_at', '>=', Carbon::now()->subWeek());
                                                 })
                                                 ->count(),
                'top_performer' => User::where('role', '!=', 'admin')
                                      ->orderBy('total_verified_points', 'desc')
                                      ->first(['id', 'name', 'total_verified_points']),
                'needs_attention' => User::where('role', '!=', 'admin')
                                        ->where('total_verified_points', '<', 0)
                                        ->get(['id', 'name', 'total_verified_points']),
                'recent_achievements' => PointAssignment::where('status', 'verified')
                                                       ->where('points', '>=', 5)
                                                       ->where('created_at', '>=', Carbon::now()->subWeek())
                                                       ->with(['assignor:id,name', 'recipient:id,name'])
                                                       ->latest()
                                                       ->take(5)
                                                       ->get(),
                'pending_by_priority' => [
                    'urgent' => PointAssignment::where('status', 'pending')
                                              ->where('created_at', '<', Carbon::now()->subDays(3))
                                              ->count(),
                    'high' => PointAssignment::where('status', 'pending')
                                            ->where('points', '>=', 5)
                                            ->count(),
                    'normal' => PointAssignment::where('status', 'pending')
                                              ->where('points', '<', 5)
                                              ->where('points', '>', -3)
                                              ->count(),
                    'negative' => PointAssignment::where('status', 'pending')
                                                ->where('points', '<', 0)
                                                ->count()
                ]
            ];

            return response()->json($teamSummary);
        } catch (\Exception $e) {
            Log::error('Error in SupervisorController@getTeamSummary: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to load team summary'], 500);
        }
    }

    /**
     * Bulk reject multiple assignments
     */
    public function bulkReject(Request $request)
    {
        try {
            // Check if user is supervisor or admin
            if (!in_array(Auth::user()->role, ['supervisor', 'admin'])) {
                return response()->json(['error' => 'Access denied'], 403);
            }

            $request->validate([
                'assignment_ids' => 'required|array',
                'assignment_ids.*' => 'exists:point_assignments,id',
                'rejection_reason' => 'required|string|min:3|max:500'
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
                    'verified_at' => now(),
                    'rejection_reason' => $request->rejection_reason
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
                    'assignment_ids' => $request->assignment_ids,
                    'rejection_reason' => $request->rejection_reason
                ]
            ]);

            return response()->json([
                'message' => "Successfully rejected {$rejectedCount} point assignments",
                'rejected_count' => $rejectedCount
            ]);
        } catch (\Exception $e) {
            Log::error('Error in SupervisorController@bulkReject: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to bulk reject assignments'], 500);
        }
    }

    /**
     * Get supervisor activity summary
     */
    public function getActivitySummary()
    {
        try {
            // Check if user is supervisor or admin
            if (!in_array(Auth::user()->role, ['supervisor', 'admin'])) {
                return response()->json(['error' => 'Access denied'], 403);
            }

            $userId = Auth::id();
            $oneWeekAgo = Carbon::now()->subWeek();
            $oneMonthAgo = Carbon::now()->subMonth();

            $summary = [
                'points_assigned_this_week' => PointAssignment::where('assignor_id', $userId)
                                                             ->where('created_at', '>=', $oneWeekAgo)
                                                             ->sum('points'),
                'points_approved_this_week' => PointAssignment::where('verified_by', $userId)
                                                             ->where('verified_at', '>=', $oneWeekAgo)
                                                             ->where('status', 'verified')
                                                             ->sum('points'),
                'assignments_reviewed_this_month' => PointAssignment::where('verified_by', $userId)
                                                                   ->where('verified_at', '>=', $oneMonthAgo)
                                                                   ->count(),
                'average_review_time' => $this->getAverageReviewTime(),
                'recent_reviews' => PointAssignment::where('verified_by', $userId)
                                                  ->with(['assignor:id,name', 'recipient:id,name'])
                                                  ->latest('verified_at')
                                                  ->take(10)
                                                  ->get()
            ];

            return response()->json($summary);
        } catch (\Exception $e) {
            Log::error('Error in SupervisorController@getActivitySummary: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to load activity summary'], 500);
        }
    }

    /**
     * Helper method to calculate average review time
     */
    private function getAverageReviewTime()
    {
        $userId = Auth::id();
        $oneMonthAgo = Carbon::now()->subMonth();

        $reviewedAssignments = PointAssignment::where('verified_by', $userId)
                                            ->where('verified_at', '>=', $oneMonthAgo)
                                            ->whereNotNull('verified_at')
                                            ->select('created_at', 'verified_at')
                                            ->get();

        if ($reviewedAssignments->isEmpty()) {
            return 0;
        }

        $totalHours = 0;
        foreach ($reviewedAssignments as $assignment) {
            $totalHours += $assignment->created_at->diffInHours($assignment->verified_at);
        }

        return round($totalHours / $reviewedAssignments->count(), 1);
    }
}