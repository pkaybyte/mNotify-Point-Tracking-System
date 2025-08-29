<?php

namespace App\Mail;

use App\Models\PointAssignment;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PointRejectedNotification extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public PointAssignment $pointAssignment,
        public string $userType // 'assignor' or 'recipient'
    ) {}

    public function envelope(): Envelope
    {
        $points = $this->pointAssignment->points;
        $subject = $this->userType === 'assignor' 
            ? "Point Assignment Rejected - {$points} points you assigned were rejected"
            : "Point Assignment Rejected - {$points} points assigned to you were rejected";
            
        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.point-rejected',
            with: [
                'assignment' => $this->pointAssignment,
                'recipient' => $this->pointAssignment->recipient,
                'assignor' => $this->pointAssignment->assignor,
                'userType' => $this->userType,
                'rejectionReason' => $this->pointAssignment->rejection_reason,
                'verifier' => $this->pointAssignment->verifier
            ]
        );
    }
}
