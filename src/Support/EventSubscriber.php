<?php

namespace IbraheemGhazi\LaravelAiFlow\Support;

use IbraheemGhazi\LaravelAiFlow\Support\FlowProgressQueueListener;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\JobQueued;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;

class EventSubscriber
{
    
    public static function subscribe()
    {
        Event::listen(JobQueued::class, [FlowProgressQueueListener::class, 'pending']);

        Queue::before(function (JobProcessing $event): void {
            FlowProgressQueueListener::running($event);
        });

        Queue::failing(function (JobFailed $event): void {
            FlowProgressQueueListener::failed($event);
        });

        Queue::after(function (JobProcessed $event): void {
            FlowProgressQueueListener::finished($event);
        });
    }
    
}
