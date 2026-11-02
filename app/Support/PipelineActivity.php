<?php

namespace App\Support;

use App\Models\Pipeline;
use App\Models\PipelineTouch;
use App\Models\User;
use App\Notifications\PipelineTouchedNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

class PipelineActivity
{
    public function record(Pipeline $pipeline, ?User $actor, string $action): void
    {
        $now = now();
        $pipeline->forceFill(['last_activity_at' => $now])->saveQuietly();

        if ($actor === null) {
            return;
        }

        $recipients = $this->recipientsFor($pipeline, $actor);

        PipelineTouch::updateOrCreate(
            ['pipeline_id' => $pipeline->id, 'user_id' => $actor->id],
            ['last_touched_at' => $now],
        );

        if ($recipients->isNotEmpty()) {
            Notification::send($recipients, new PipelineTouchedNotification($pipeline->fresh(), $actor, $action));
        }
    }

    /** @return Collection<int, User> */
    private function recipientsFor(Pipeline $pipeline, User $actor): Collection
    {
        $pipeline->loadMissing('siteSurvey');

        $userIds = collect([
            $pipeline->project_manager_id,
            $pipeline->assigned_saq_id,
            $pipeline->siteSurvey?->surveyor_id,
            $pipeline->siteSurvey?->survey_completed_by_id,
            $pipeline->siteSurvey?->network_feasibility_completed_by_id,
        ])
            ->merge($this->previousToucherIds($pipeline))
            ->merge($this->pipelineActorIds($pipeline))
            ->filter()
            ->reject(fn (int $userId): bool => $userId === $actor->id)
            ->unique()
            ->values();

        if ($userIds->isEmpty()) {
            return collect();
        }

        return User::query()
            ->whereKey($userIds)
            ->get();
    }

    /** @return Collection<int, int> */
    private function previousToucherIds(Pipeline $pipeline): Collection
    {
        return PipelineTouch::query()
            ->where('pipeline_id', $pipeline->id)
            ->pluck('user_id');
    }

    /** @return Collection<int, int|null> */
    private function pipelineActorIds(Pipeline $pipeline): Collection
    {
        $documentUploaderIds = $pipeline->documents()->pluck('uploaded_by');

        if ($pipeline->siteSurvey !== null) {
            $documentUploaderIds = $documentUploaderIds
                ->merge($pipeline->siteSurvey->documents()->pluck('uploaded_by'));
        }

        return collect()
            ->merge($pipeline->statusLogs()->pluck('changed_by'))
            ->merge($pipeline->remarkLogs()->pluck('added_by'))
            ->merge($pipeline->approvals()->pluck('decided_by_id'))
            ->merge($documentUploaderIds);
    }

    /** @return array{label: string, level: string, classes: string}|null */
    public function agingBadge(Pipeline $pipeline, array $thresholds): ?array
    {
        $activityAt = $pipeline->last_activity_at ?? $pipeline->status_changed_at ?? $pipeline->updated_at;

        if ($activityAt === null) {
            return null;
        }

        $days = (int) floor($activityAt->diffInHours(now()) / 24);

        if ($days >= $thresholds['red_days']) {
            return [
                'label' => "{$days}d",
                'level' => 'red',
                'classes' => 'bg-red-50 text-red-700 border border-red-200',
            ];
        }

        if ($days >= $thresholds['yellow_days']) {
            return [
                'label' => "{$days}d",
                'level' => 'yellow',
                'classes' => 'bg-amber-50 text-amber-700 border border-amber-200',
            ];
        }

        return [
            'label' => "{$days}d",
            'level' => 'green',
            'classes' => 'bg-green-50 text-green-700 border border-green-200',
        ];
    }
}
