<?php

namespace App\Enums;

enum ProjectStage: string
{
    case Planning = 'Planning';
    case Execution = 'Execution';
    case Monitoring = 'Monitoring';
    case Closing = 'Closing';

    public function label(): string
    {
        return match ($this) {
            self::Planning => 'Project Planning',
            self::Execution => 'Project Execution',
            self::Monitoring => 'Monitoring and Controlling',
            self::Closing => 'Project Closing',
        };
    }

    public function order(): int
    {
        return match ($this) {
            self::Planning => 1,
            self::Execution => 2,
            self::Monitoring => 3,
            self::Closing => 4,
        };
    }

    public function next(): ?self
    {
        return match ($this) {
            self::Planning => self::Execution,
            self::Execution => self::Monitoring,
            self::Monitoring => self::Closing,
            self::Closing => null,
        };
    }

    public function previous(): ?self
    {
        return match ($this) {
            self::Planning => null,
            self::Execution => self::Planning,
            self::Monitoring => self::Execution,
            self::Closing => self::Monitoring,
        };
    }

    /** @return array<int, self> */
    public static function ordered(): array
    {
        return [self::Planning, self::Execution, self::Monitoring, self::Closing];
    }
}
