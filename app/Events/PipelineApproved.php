<?php

namespace App\Events;

use App\Models\Pipeline;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PipelineApproved
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly Pipeline $pipeline) {}
}
