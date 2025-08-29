<?php

namespace App\Mail;

use App\Models\PointAssignment;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AdminPointAssignedNotification extends Mailable
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
            subject: "Point Assignment Alert - {$type} points assigned ({$points} points)"
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.admin-point-assigned',
            with: [
                'assignment' => $this->pointAssignment,
                'recipient' => $this->pointAssignment->recipient,
                'assignor' => $this->pointAssignment->assignor,
                'isPositive' => $this->pointAssignment->isPositive()
            ]
        );
    }
}
