<?php

namespace App\Message;

final readonly class GenerateModerationResultMessage
{
    public function __construct(
        public int $jokeId,
    ) {
    }
}
