<?php

namespace App\Listeners;

use App\Events\PipelineApproved;
use App\Support\PipelineNotifier;

class NotifyProjectCreated
{
    public function handle(PipelineApproved $event): void
    {
        app(PipelineNotifier::class)->projectCreated($event->pipeline->fresh());
    }
}
