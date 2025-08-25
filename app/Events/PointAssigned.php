<?php
// app/Events/PointAssigned.php
namespace App\Events;

use App\Models\PointAssignment;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PointAssigned
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public PointAssignment $pointAssignment
    ) {}
}