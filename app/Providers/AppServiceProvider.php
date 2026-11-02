<?php

namespace App\Providers;

use App\Models\NetworkSiteAssignment;
use App\Models\Pipeline;
use App\Models\Project;
use App\Models\SitePlanningAssignment;
use App\Policies\NetworkPolicy;
use App\Policies\PipelinePolicy;
use App\Policies\ProjectPolicy;
use App\Policies\SitePlanningPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    /**
     * App\Listeners\InitializeProject and App\Listeners\NotifyProjectCreated are not registered
     * here — both already match Laravel's event auto-discovery convention (a public handle()
     * method type-hinted with PipelineApproved), so an explicit Event::listen() call here would
     * register them a second time and fire them twice per event.
     */
    public function boot(): void
    {
        Gate::policy(Pipeline::class, PipelinePolicy::class);
        Gate::policy(Project::class, ProjectPolicy::class);
        Gate::policy(NetworkSiteAssignment::class, NetworkPolicy::class);
        Gate::policy(SitePlanningAssignment::class, SitePlanningPolicy::class);
    }
}
