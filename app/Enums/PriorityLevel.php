<?php

namespace App\Enums;

enum PriorityLevel: string
{
    case Critical = 'Critical';
    case High = 'High';
    case Medium = 'Medium';
    case Low = 'Low';
}
