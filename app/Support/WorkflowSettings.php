<?php

namespace App\Support;

use App\Models\WorkflowSetting;

class WorkflowSettings
{
    public const ALARM_THRESHOLDS = 'alarm_thresholds';

    /** @return array{yellow_days: int, red_days: int} */
    public function alarmThresholds(): array
    {
        $value = WorkflowSetting::query()
            ->where('key', self::ALARM_THRESHOLDS)
            ->first()
            ?->value;

        $thresholds = is_array($value) ? $value : [];

        return [
            'yellow_days' => max(1, (int) ($thresholds['yellow_days'] ?? 2)),
            'red_days' => max(1, (int) ($thresholds['red_days'] ?? 4)),
        ];
    }

    public function updateAlarmThresholds(int $yellowDays, int $redDays): void
    {
        WorkflowSetting::updateOrCreate(
            ['key' => self::ALARM_THRESHOLDS],
            ['value' => [
                'yellow_days' => $yellowDays,
                'red_days' => $redDays,
            ]],
        );
    }
}
