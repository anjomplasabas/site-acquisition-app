<?php

namespace App\Enums;

enum PipelineStatus: string
{
    case Prospect = 'Prospect';
    case Prospecting = 'Prospecting';
    case SiteSurveyPending = 'Site Survey Pending';
    case SiteSurvey = 'Site Survey';
    case Commercials = 'Commercials';
    case Approval = 'Approval';
    case ReadyForDeployment = 'RFD';
    case ReadyForService = 'RFS';
    case Inactive = 'Inactive';
    case Completed = 'Completed';

    /** @return array<string, string> */
    public static function legacyMap(): array
    {
        return [
            'Initiated' => self::Prospecting->value,
            'Site Visit Approval' => self::Prospecting->value,
            'Pending Site Visit' => self::SiteSurveyPending->value,
            'Ongoing Site Survey' => self::SiteSurvey->value,
            'Pending Commercials' => self::Commercials->value,
            'Ongoing Pipeline Approval' => self::Approval->value,
        ];
    }

    /** @return array<string> */
    public static function activeValues(): array
    {
        return [
            self::Prospect->value,
            self::Prospecting->value,
            self::SiteSurveyPending->value,
            self::SiteSurvey->value,
            self::Commercials->value,
            self::Approval->value,
            self::ReadyForDeployment->value,
            self::ReadyForService->value,
        ];
    }
}
