<?php

namespace App\Support;

class LeadName
{
    public static function normalize(?string $name): ?string
    {
        if ($name === null) {
            return null;
        }

        $normalized = str($name)->lower()->squish()->toString();

        return $normalized === '' ? null : $normalized;
    }
}
