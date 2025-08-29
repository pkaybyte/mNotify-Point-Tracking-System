<?php
// app/Listeners/SendPointVerifiedNotification.php
namespace App\Listeners;

use App\Events\PointVerified;
use App\Mail\PointVerifiedMail;
use App\Mail\PointRejectedNotification;
use Illuminate\Support\Facades\Mail;

class SendPointVerifiedNotification
{
    public function handle(PointVerified $event): void
    {
        $assignment = $event->pointAssignment;
        $recipient = $assignment->recipient;
        $assignor = $assignment->assignor;
        
        // Handle verified points (approved)
        if ($assignment->status === 'verified' && $event->previousStatus === 'pending') {
            // Send verification email to recipient if enabled
            if ($recipient->email_on_point_verified) {
                Mail::to($recipient)->queue(
                    new PointVerifiedMail($assignment, $assignment->status)
                );
            }
        }
        
        // Handle rejected points
        if ($assignment->status === 'rejected' && $event->previousStatus === 'pending') {
            // Send rejection notification to both assignor and recipient
            
            // Notify the person who assigned the points (assignor)
            if ($assignor && $assignor->email_on_point_verified) {
                Mail::to($assignor)->queue(
                    new PointRejectedNotification($assignment, 'assignor')
                );
            }
            
            // Notify the person who was supposed to receive the points (recipient)
            if ($recipient->email_on_point_verified) {
                Mail::to($recipient)->queue(
                    new PointRejectedNotification($assignment, 'recipient')
                );
            }
        }
    }
}