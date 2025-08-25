<?php
// app/Listeners/SendPointVerifiedNotification.php
namespace App\Listeners;

use App\Events\PointVerified;
use App\Mail\PointVerifiedMail;
use Illuminate\Support\Facades\Mail;

class SendPointVerifiedNotification
{
    public function handle(PointVerified $event): void
    {
        $assignment = $event->pointAssignment;
        $recipient = $assignment->recipient;
        
        // Only send if user has email notifications enabled and status changed from pending
        if ($recipient->email_on_point_verified && $event->previousStatus === 'pending') {
            Mail::to($recipient)->queue(
                new PointVerifiedMail($assignment, $assignment->status)
            );
        }
    }
}