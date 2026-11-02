<?php

namespace App\Enums;

enum DocumentType: string
{
    case Photo = 'photo';
    case Document = 'document';
    case Video = 'video';
}
