<?php

namespace App\Listeners;

use App\Events\PointAssigned;
use App\Mail\PointAssignedMail;
use App\Mail\PendingPointsNotificationMail; // 🔥 CORRECT IMPORT - from App\Mail namespace
use App\Models\PointAssignment;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

class SendPointAssignedNotification
{
    /**
     * Handle the event.
     */
    public function handle(PointAssigned $event): void
    {
        $assignment = $event->pointAssignment;
        $recipient = $assignment->recipient;
        
        // Only send if user has email notifications enabled
        if ($recipient->email_on_point_received) {
            Mail::to($recipient)->queue(new PointAssignedMail($assignment));
        }
        
        // If it's an unverified assignment, notify supervisors
        if ($assignment->status === 'pending') {
            $this->notifySupervisors();
        }
    }
    
    /**
     * Notify supervisors about pending points
     */
    private function notifySupervisors(): void
    {
        $supervisors = User::where('role', 'supervisor')
            ->where('email_on_pending_points', true)
            ->get();
            
        foreach ($supervisors as $supervisor) {
            $pendingAssignments = PointAssignment::where('status', 'pending')
                ->with(['assignor', 'recipient'])
                ->get();
                
            if ($pendingAssignments->count() > 0) {
                Mail::to($supervisor)->queue(
                    new PendingPointsNotificationMail($supervisor, $pendingAssignments)
                );
            }
        }
    }
}