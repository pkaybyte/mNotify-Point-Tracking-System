<?php
// app/Providers/EventServiceProvider.php
namespace App\Providers;

use App\Events\PointAssigned;
use App\Events\PointVerified;
use App\Listeners\SendPointAssignedNotification;
use App\Listeners\SendPointVerifiedNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        PointAssigned::class => [
            SendPointAssignedNotification::class,
        ],
        PointVerified::class => [
            SendPointVerifiedNotification::class,
        ],
    ];

    public function boot(): void
    {
        //
    }
}