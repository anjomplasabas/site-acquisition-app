<?php

namespace App\Policies;

use App\Enums\NetworkActionPermission;
use App\Models\NetworkSiteAssignment;
use App\Models\User;

class NetworkPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->role?->name === 'SUPER_ADMIN' ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $this->hasAction($user, NetworkActionPermission::ViewNetworkAssignments);
    }

    public function view(User $user, NetworkSiteAssignment $networkSiteAssignment): bool
    {
        return $this->hasAction($user, NetworkActionPermission::ViewNetworkAssignments);
    }

    /**
     * Once Network Feasibility is marked Completed, further edits are locked — re-entering
     * Site Survey (reopen, or an Approval rejection) resets network_feasibility_completed_at
     * via SiteSurveyProgression::enterSiteSurvey(), which reopens this gate automatically.
     */
    public function manageFeasibility(User $user, NetworkSiteAssignment $networkSiteAssignment): bool
    {
        if ($networkSiteAssignment->siteSurvey?->network_feasibility_completed_at !== null) {
            return false;
        }

        return $this->hasAction($user, NetworkActionPermission::ManageNetworkFeasibility);
    }

    /**
     * Deliberately does not delegate to manageFeasibility() — completeFeasibility() itself
     * already guards against double-completion with a friendly flash message, and gating the
     * permission here too would turn that into a raw 403 instead.
     */
    public function completeFeasibility(User $user, NetworkSiteAssignment $networkSiteAssignment): bool
    {
        return $this->hasAction($user, NetworkActionPermission::ManageNetworkFeasibility);
    }

    private function hasAction(User $user, NetworkActionPermission $permission): bool
    {
        return $user->role?->hasActionPermission($permission->value) ?? false;
    }
}
