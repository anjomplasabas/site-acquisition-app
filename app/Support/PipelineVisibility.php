<?php

namespace App\Support;

use App\Enums\PipelineActionPermission;
use App\Enums\PipelineStatus;
use App\Enums\SitePlanningActionPermission;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class PipelineVisibility
{
    public static function apply(Builder $query, User $user): void
    {
        if ($user->role?->hasActionPermission(PipelineActionPermission::ViewAll->value)) {
            return;
        }

        $permissions = [
            'assignedSaq' => $user->role?->hasActionPermission(PipelineActionPermission::ViewAssignedSaq->value) ?? false,
            'assignedSurveyor' => $user->role?->hasActionPermission(SitePlanningActionPermission::FillSurvey->value) ?? false,
            'commercials' => $user->role?->hasActionPermission(PipelineActionPermission::HandleCommercials->value) ?? false,
            'approvalDecision' => ($user->role?->hasActionPermission(PipelineActionPermission::DecideApproval->value) ?? false)
                && ($user->role?->hasPermissionFor(PipelineStatus::Approval) ?? false),
        ];

        if (! in_array(true, $permissions, true)) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->where(function (Builder $query) use ($permissions, $user) {
            if ($permissions['assignedSaq']) {
                $query->orWhere('assigned_saq_id', $user->id);
            }

            if ($permissions['assignedSurveyor']) {
                $query->orWhereHas('siteSurvey', fn (Builder $query) => $query->where('surveyor_id', $user->id));
            }

            if ($permissions['commercials']) {
                $query->orWhereIn('status', [
                    PipelineStatus::Commercials->value,
                    PipelineStatus::Approval->value,
                ]);
            }

            if ($permissions['approvalDecision']) {
                $query->orWhere('status', PipelineStatus::Approval->value);
            }
        });
    }
}
