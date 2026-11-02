<?php

namespace App\Enums;

enum TaskTemplateContext: string
{
    case SitePlanning = 'site_planning';
    case Monitoring = 'monitoring';

    public function label(): string
    {
        return match ($this) {
            self::SitePlanning => 'Site Planning (Plans/Drawing)',
            self::Monitoring => 'Monitoring (Infrastructure Deployment Status)',
        };
    }
}
