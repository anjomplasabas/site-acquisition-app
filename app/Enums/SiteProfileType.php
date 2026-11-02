<?php

namespace App\Enums;

enum SiteProfileType: string
{
    case Condo = 'Condominium';
    case School = 'School';
    case Dormitory = 'Dormitory';
    case Apartment = 'Apartment';
    case RowHouse = 'ROW House';
    case Others = 'Others';

    // Legacy types no longer offered for new sites — kept so existing site profiles remain valid.
    case Village = 'Village';
    case Barangay = 'Barangay';
    case Housing = 'Housing';

    /**
     * The types offered in the Type dropdown — every case except the legacy ones.
     *
     * @return list<string>
     */
    public static function selectable(): array
    {
        return array_column(array_filter(self::cases(), fn (self $type): bool => ! in_array($type, [self::Village, self::Barangay, self::Housing], true)), 'value');
    }
}
