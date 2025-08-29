<?php
// app/Mail/PendingPointsNotificationMail.php
namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class PendingPointsNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $supervisor,
        public Collection $pendingAssignments
    ) {}

    public function envelope(): Envelope
    {
        $count = $this->pendingAssignments->count();
        
        return new Envelope(
            subject: "You have {$count} pending point assignments to review"
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.pending-points-notification',
            with: [
                'supervisor' => $this->supervisor,
                'pendingAssignments' => $this->pendingAssignments,
                'count' => $this->pendingAssignments->count()
            ]
        );
    }
}