<?php

namespace App\Enum;

enum ModerationRecommendation: string
{
    case Approve = 'approve';
    case Reject = 'reject';
    case Unsure = 'unsure';
}
