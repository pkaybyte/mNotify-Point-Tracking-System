<?php
// app/Mail/PointVerifiedMail.php
namespace App\Mail;

use App\Models\PointAssignment;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PointVerifiedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public PointAssignment $pointAssignment,
        public string $status // 'verified' or 'rejected'
    ) {}

    public function envelope(): Envelope
    {
        $action = $this->status === 'verified' ? 'approved' : 'rejected';
        
        return new Envelope(
            subject: "Your points have been {$action}"
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.point-verified',
            with: [
                'assignment' => $this->pointAssignment,
                'recipient' => $this->pointAssignment->recipient,
                'verifier' => $this->pointAssignment->verifier,
                'status' => $this->status,
                'isApproved' => $this->status === 'verified'
            ]
        );
    }
}