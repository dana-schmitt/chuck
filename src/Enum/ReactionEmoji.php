<?php

namespace App\Enum;

/**
 * The fixed set of emoji reactions users can add to a comment.
 */
enum ReactionEmoji: string
{
    case ThumbsUp = '👍';
    case Laugh = '😂';
    case Heart = '❤️';
    case Shocked = '😮';
}
