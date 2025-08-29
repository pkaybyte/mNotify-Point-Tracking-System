<?php

namespace App\Http\Controllers;

use App\Models\PointAssignment;
use App\Models\User;
use App\Models\AuditLog;
use App\Events\PointAssigned;
use App\Events\PointVerified;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class PointAssignmentController extends Controller
{
    // Assign points
    public function store(Request $request)
    {
        $request->validate([
            'recipient_id' => 'required|exists:users,id',
            'reason' => 'required|string',
            'points' => 'required|integer|not_in:0', // Added points validation
        ]);

        $assignor = Auth::user();
        $status = $assignor->role === 'supervisor' || $assignor->role === 'admin' ? 'verified' : 'pending';

        $assignment = PointAssignment::create([
            'assignor_id' => Auth::id(),
            'recipient_id' => $request->recipient_id,
            'points' => $request->points,
            'reason' => $request->reason,
            'status' => $status,
            'verified_by' => $status === 'verified' ? Auth::id() : null,
            'verified_at' => $status === 'verified' ? now() : null,
        ]);

        // Update recipient's total verified points if auto-verified (supervisor/admin assignment)
        if ($status === 'verified') {
            $recipient = User::find($request->recipient_id);
            $recipient->increment('total_verified_points', $request->points);
        }

        // 🔥 TRIGGER EMAIL EVENT
        event(new PointAssigned($assignment->load(['assignor', 'recipient'])));

        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => 'assigned_point',
            'data' => [
                'recipient_id' => $request->recipient_id,
                'points' => $request->points,
                'reason' => $request->reason,
                'status' => $status,
            ]
        ]);

        return response()->json([
            'message' => 'Points assigned successfully', 
            'data' => $assignment->load(['assignor', 'recipient'])
        ], 201);
    }

    // Get points data for chart
    public function getPoints()
    {
        $userId = Auth::id();
        
        // For admin, show system-wide data
        if (Auth::user()->role === 'admin') {
            $verifiedPoints = PointAssignment::where('status', 'verified')->sum('points');
            $unverifiedPoints = PointAssignment::where('status', 'pending')->sum('points');
            $totalAssignments = PointAssignment::count();
            $verifiedCount = PointAssignment::where('status', 'verified')->count();
            $pendingCount = PointAssignment::where('status', 'pending')->count();
        } else {
            // For users/supervisors, show personal data
            $verifiedPoints = PointAssignment::where('recipient_id', $userId)
                                            ->where('status', 'verified')
                                            ->sum('points');
            $unverifiedPoints = PointAssignment::where('recipient_id', $userId)
                                              ->where('status', 'pending')
                                              ->sum('points');
            $totalAssignments = PointAssignment::where('recipient_id', $userId)->count();
            $verifiedCount = PointAssignment::where('recipient_id', $userId)
                                           ->where('status', 'verified')
                                           ->count();
            $pendingCount = PointAssignment::where('recipient_id', $userId)
                                          ->where('status', 'pending')
                                          ->count();
        }

        return response()->json([
            'verified_points' => $verifiedPoints,
            'unverified_points' => $unverifiedPoints,
            'total_assignments' => $totalAssignments,
            'verified_count' => $verifiedCount,
            'pending_count' => $pendingCount,
            'chart_data' => [
                'labels' => ['Verified Points', 'Pending Points'],
                'datasets' => [
                    [
                        'data' => [$verifiedPoints, $unverifiedPoints],
                        'backgroundColor' => ['#10B981', '#F59E0B']
                    ]
                ]
            ]
        ]);
    }

    // Approve a pending point assignment
    public function approve(PointAssignment $assignment)
    {
        $user = Auth::user();
        
        // Check if user has permission (admin or supervisor)
        if (!in_array($user->role, ['supervisor', 'admin'])) {
            return response()->json(['error' => 'Only supervisors and admins can approve assignments'], 403);
        }

        // Store previous status for email notification
        $previousStatus = $assignment->status;

        // Check if assignment is already verified or rejected
        if ($assignment->status !== 'pending') {
            return response()->json([
                'message' => 'This assignment has already been processed'
            ], 400);
        }

        $assignment->update([
            'status' => 'verified',
            'verified_by' => Auth::id(),
            'verified_at' => now(),
        ]);

        // Update recipient's total verified points
        $recipient = $assignment->recipient;
        $recipient->increment('total_verified_points', $assignment->points);

        // 🔥 TRIGGER EMAIL EVENT
        event(new PointVerified($assignment->load(['assignor', 'recipient', 'verifier']), $previousStatus));

        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => 'approved_point',
            'data' => ['assignment_id' => $assignment->id]
        ]);

        return response()->json([
            'message' => 'Point assignment approved',
            'data' => $assignment->load(['assignor', 'recipient', 'verifier'])
        ]);
    }

    // Reject a pending point assignment
    public function reject(Request $request, PointAssignment $assignment)
    {
        $user = Auth::user();
        
        // Check if user has permission (admin or supervisor)
        if (!in_array($user->role, ['supervisor', 'admin'])) {
            return response()->json(['error' => 'Only supervisors and admins can reject assignments'], 403);
        }

        // Validate rejection reason
        $request->validate([
            'rejection_reason' => 'required|string|min:3|max:500'
        ]);

        // Store previous status for email notification
        $previousStatus = $assignment->status;

        // Check if assignment is already verified or rejected
        if ($assignment->status !== 'pending') {
            return response()->json([
                'message' => 'This assignment has already been processed'
            ], 400);
        }

        $assignment->update([
            'status' => 'rejected',
            'verified_by' => Auth::id(),
            'verified_at' => now(),
            'rejection_reason' => $request->rejection_reason,
        ]);

        // 🔥 TRIGGER EMAIL EVENT
        event(new PointVerified($assignment->load(['assignor', 'recipient', 'verifier']), $previousStatus));

        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => 'rejected_point',
            'data' => [
                'assignment_id' => $assignment->id,
                'rejection_reason' => $request->rejection_reason
            ]
        ]);

        return response()->json([
            'message' => 'Point assignment rejected',
            'data' => $assignment->load(['assignor', 'recipient', 'verifier'])
        ]);
    }

    // Bulk assign points to all users (Supervisor/Admin only)
    public function bulkAssign(Request $request)
    {
        $request->validate([
            'points' => 'required|integer|not_in:0',
            'reason' => 'required|string',
        ]);

        $assignor = Auth::user();

        // Only supervisors and admins can do bulk assignment
        if (!in_array($assignor->role, ['supervisor', 'admin'])) {
            return response()->json(['error' => 'Only supervisors and admins can bulk assign points'], 403);
        }

        // Get all users who can receive points (not admins and not the assignor)
        $recipients = User::where('role', '!=', 'admin')
                         ->where('id', '!=', $assignor->id)
                         ->get();

        $assignments = [];
        
        foreach ($recipients as $recipient) {
            $assignment = PointAssignment::create([
                'assignor_id' => $assignor->id,
                'recipient_id' => $recipient->id,
                'points' => $request->points,
                'reason' => $request->reason,
                'status' => 'verified', // Supervisor/Admin assignments are auto-verified
                'verified_by' => $assignor->id,
                'verified_at' => now(),
                'is_bulk_assignment' => true
            ]);

            // Update recipient's total
            $recipient->increment('total_verified_points', $request->points);
            
            // 🔥 TRIGGER EMAIL EVENT for each assignment
            event(new PointAssigned($assignment->load(['assignor', 'recipient'])));
            
            $assignments[] = $assignment;
        }

        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => 'bulk_assigned_points',
            'data' => [
                'points' => $request->points,
                'reason' => $request->reason,
                'recipients_count' => count($assignments),
            ]
        ]);

        return response()->json([
            'message' => "Points assigned to {$recipients->count()} users",
            'assignments_count' => count($assignments)
        ]);
    }

    // Get pending assignments for supervisor/admin dashboard
    public function getPendingAssignments()
    {
        $user = Auth::user();
        
        if (!in_array($user->role, ['supervisor', 'admin'])) {
            return response()->json(['error' => 'Access denied'], 403);
        }

        $pendingAssignments = PointAssignment::where('status', 'pending')
                                           ->with(['assignor:id,name', 'recipient:id,name'])
                                           ->orderBy('created_at', 'desc')
                                           ->get();

        return response()->json([
            'pending_assignments' => $pendingAssignments,
            'count' => $pendingAssignments->count()
        ]);
    }

    // Enhanced method for detailed pending assignments
    public function getPendingAssignmentsDetailed()
    {
        $user = Auth::user();
        
        if (!in_array($user->role, ['supervisor', 'admin'])) {
            return response()->json(['error' => 'Access denied'], 403);
        }

        $pendingAssignments = PointAssignment::where('status', 'pending')
                                           ->with(['assignor:id,name,email', 'recipient:id,name,email'])
                                           ->orderBy('created_at', 'desc')
                                           ->get()
                                           ->map(function ($assignment) {
                                               return [
                                                   'id' => $assignment->id,
                                                   'assignor' => $assignment->assignor,
                                                   'recipient' => $assignment->recipient,
                                                   'points' => $assignment->points,
                                                   'reason' => $assignment->reason,
                                                   'created_at' => $assignment->created_at->toISOString(),
                                                   'days_pending' => $assignment->created_at->diffInDays(now()),
                                                   'urgency' => $assignment->created_at->diffInDays(now()) >= 3 ? 'urgent' : 'normal'
                                               ];
                                           });

        return response()->json([
            'pending_assignments' => $pendingAssignments,
            'count' => $pendingAssignments->count()
        ]);
    }

    // List all assignments for the logged-in user (assignments they made)
    public function myAssignments()
    {
        $assignments = PointAssignment::where('assignor_id', Auth::id()) // Fixed: should be assignor_id for "my assignments"
                                     ->with(['recipient:id,name'])
                                     ->orderBy('created_at', 'desc')
                                     ->get();
        return response()->json($assignments);
    }

    // Get assignments received by the logged-in user
    public function getReceivedAssignments()
    {
        $assignments = PointAssignment::where('recipient_id', Auth::id())
                                     ->with(['assignor:id,name'])
                                     ->orderBy('created_at', 'desc')
                                     ->get();
        return response()->json($assignments);
    }

    // Get supervisor stats
    public function getSupervisorStats()
    {
        $user = Auth::user();
        
        if (!in_array($user->role, ['supervisor', 'admin'])) {
            return response()->json(['error' => 'Access denied'], 403);
        }

        $stats = [
            'totalTeamMembers' => User::where('role', '!=', 'admin')->count(),
            'pendingReviews' => PointAssignment::where('status', 'pending')->count(),
            'approvedThisWeek' => PointAssignment::where('status', 'verified')
                                               ->where('created_at', '>=', now()->startOfWeek())
                                               ->count(),
            'averageTeamPoints' => round(User::where('role', '!=', 'admin')->avg('total_verified_points') ?? 0, 1)
        ];

        return response()->json($stats);
    }

    /**
     * Get point assignment logs for the dashboard
     */
    public function getPointLogs()
    {
        $currentUserId = Auth::id();
        $currentUser = Auth::user();
        
        // For admin, show all assignments. For others, show only related assignments
        if ($currentUser->role === 'admin') {
            $query = PointAssignment::with(['assignor:id,name', 'recipient:id,name'])
                                   ->orderBy('created_at', 'desc');
        } else {
            $query = PointAssignment::with(['assignor:id,name', 'recipient:id,name'])
                                   ->where(function ($query) use ($currentUserId) {
                                       $query->where('assignor_id', $currentUserId)
                                             ->orWhere('recipient_id', $currentUserId);
                                   })
                                   ->orderBy('created_at', 'desc');
        }
        
        $logs = $query->get()->map(function ($assignment) use ($currentUserId, $currentUser) {
            return [
                'id' => $assignment->id,
                'assignor_id' => $assignment->assignor_id,
                'recipient_id' => $assignment->recipient_id,
                'assignor_name' => $assignment->assignor_id === $currentUserId 
                    ? 'You' 
                    : ($assignment->assignor->name ?? 'Unknown'),
                'recipient_name' => $assignment->recipient_id === $currentUserId 
                    ? 'You' 
                    : ($assignment->recipient->name ?? 'Unknown'),
                'points' => $assignment->points,
                'reason' => $assignment->reason,
                'status' => $assignment->status,
                'created_at' => $assignment->created_at->toISOString(),
                'verified_at' => $assignment->verified_at ? $assignment->verified_at->toISOString() : null,
                'is_admin_view' => $currentUser->role === 'admin'
            ];
        });
        
        return response()->json($logs);
    }

    /**
     * Get summary statistics for dashboard
     */
    public function getStats()
    {
        $userId = Auth::id();
        $user = Auth::user();
        
        if ($user->role === 'admin') {
            // Admin sees system-wide stats
            $stats = [
                'assigned_by_me' => PointAssignment::where('assignor_id', $userId)->count(),
                'received_by_me' => PointAssignment::where('recipient_id', $userId)->count(),
                'verified_points' => PointAssignment::where('status', 'verified')->sum('points'),
                'pending_points' => PointAssignment::where('status', 'pending')->sum('points'),
                'total_verified_points' => $user->total_verified_points ?? 0,
                'total_assignments' => PointAssignment::count(),
                'total_users' => User::count(),
                'pending_count' => PointAssignment::where('status', 'pending')->count(),
            ];
        } else {
            // Regular users see personal stats
            $stats = [
                'assigned_by_me' => PointAssignment::where('assignor_id', $userId)->count(),
                'received_by_me' => PointAssignment::where('recipient_id', $userId)->count(),
                'verified_points' => PointAssignment::where('recipient_id', $userId)
                    ->where('status', 'verified')->sum('points'),
                'pending_points' => PointAssignment::where('recipient_id', $userId)
                    ->where('status', 'pending')->sum('points'),
                'total_verified_points' => $user->total_verified_points ?? 0,
            ];
        }
        
        return response()->json($stats);
    }
}