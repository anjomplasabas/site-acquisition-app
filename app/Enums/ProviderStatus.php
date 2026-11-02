<?php

namespace App\Enums;

enum ProviderStatus: string
{
    case Checking = 'Checking';
    case Feasible = 'Feasible';
    case NonFeasible = 'Non Feasible';
}
