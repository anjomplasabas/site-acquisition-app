<?php

namespace App\Enums;

enum NetworkActionPermission: string
{
    case ViewNetworkAssignments = 'network.view_assignments';
    case ManageNetworkFeasibility = 'network.manage_feasibility';

    public function label(): string
    {
        return match ($this) {
            self::ViewNetworkAssignments => 'View Network Assignments',
            self::ManageNetworkFeasibility => 'Manage Network Feasibility',
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
