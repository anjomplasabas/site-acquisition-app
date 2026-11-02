<?php

namespace App\Enums;

enum SidebarModule: string
{
    case Dashboard = 'dashboard';
    case Pipelines = 'pipelines';
    case Projects = 'projects';
    case SitePlanning = 'site-planning';
    case Network = 'network';
    case Users = 'users';
    case Roles = 'roles';
    case Settings = 'settings';

    public function label(): string
    {
        return match ($this) {
            self::Dashboard => 'Dashboard',
            self::Pipelines => 'Pipelines',
            self::Projects => 'Projects',
            self::SitePlanning => 'Site Planning',
            self::Network => 'Network',
            self::Users => 'Users',
            self::Roles => 'Role Management',
            self::Settings => 'Settings',
        };
    }

    /** @return array<array{value: string, label: string}> */
    public static function options(): array
    {
        return array_map(
            fn (self $module): array => ['value' => $module->value, 'label' => $module->label()],
            self::cases(),
        );
    }
}
