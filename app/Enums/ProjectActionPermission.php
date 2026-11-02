<?php

namespace App\Enums;

enum ProjectActionPermission: string
{
    case ViewProjects = 'projects.view';
    case UpdateProjectStatus = 'projects.update_status';
    case ManageStageFields = 'projects.manage_stage_fields';

    public function label(): string
    {
        return match ($this) {
            self::ViewProjects => 'View Projects',
            self::UpdateProjectStatus => 'Update Project Status',
            self::ManageStageFields => 'Manage/Complete/Reopen Stage Fields',
        };
    }

    /** @return array<array{value: string, label: string}> */
    public static function options(): array
    {
        return array_map(
            fn (self $permission): array => ['value' => $permission->value, 'label' => $permission->label()],
            self::cases(),
        );
    }
}
