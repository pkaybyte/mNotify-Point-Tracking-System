<?php
// app/Mail/PointAssignedMail.php
namespace App\Mail;

use App\Models\PointAssignment;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PointAssignedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public PointAssignment $pointAssignment
    ) {}

    public function envelope(): Envelope
    {
        $points = $this->pointAssignment->points;
        $type = $points > 0 ? 'positive' : 'negative';
        
        return new Envelope(
            subject: "New {$type} points assigned to you ({$points} points)"
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.point-assigned',
            with: [
                'assignment' => $this->pointAssignment,
                'recipient' => $this->pointAssignment->recipient,
                'assignor' => $this->pointAssignment->assignor,
                'isPositive' => $this->pointAssignment->isPositive()
            ]
        );
    }
}