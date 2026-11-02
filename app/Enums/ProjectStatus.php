<?php

namespace App\Enums;

enum ProjectStatus: string
{
    case Active = 'Active';
    case OnHold = 'On Hold';
    case Completed = 'Completed';
    case Cancelled = 'Cancelled';
}
