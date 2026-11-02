<?php

namespace App\Support;

use App\Models\SiteSurvey;

/**
 * Seeds the fixed list of Additional Task Fields on the Site Survey Form — notes + file
 * attachments only, deliberately with no status field (unlike SitePlanningTask's Design Plan
 * checklist). Idempotent, mirroring SitePlanningTaskSeeder's pattern. Field names requested by
 * the Infra Department and approved by the Infra Head (2026-09-16).
 */
class SiteSurveyTaskSeeder
{
    /** @var list<string> */
    public const TASK_NAMES = [
        'MDR Room Location',
        'Service Entrance',
        'SUB MDF Location',
        'LCP & NAP Box Location',
        'Hallway Photos',
        '3U Cabinet Location',
        'Installed Cable Type (ex. Copper, Cat5e, UTP, Jacketed Wire)',
        'UPS Location',
        'EE Tapping Location Point',
        'Others',
    ];

    public static function seedMissingFor(SiteSurvey $siteSurvey): void
    {
        if ($siteSurvey->siteSurveyTasks()->exists()) {
            return;
        }

        foreach (self::TASK_NAMES as $index => $name) {
            $siteSurvey->siteSurveyTasks()->create([
                'name' => $name,
                'sort_order' => $index,
            ]);
        }
    }
}
