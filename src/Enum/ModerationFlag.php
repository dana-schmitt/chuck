<?php

namespace App\Enum;

enum ModerationFlag: string
{
    case Offensive = 'offensive';
    case NotAJoke = 'not_a_joke';
    case LowQuality = 'low_quality';
}
