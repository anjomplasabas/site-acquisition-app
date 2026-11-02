<?php

namespace App\Enums;

enum IspTaskStatus: string
{
    case Pending = 'Pending';
    case InProgress = 'In-Progress';
    case Done = 'Done';
}
