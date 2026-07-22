<?php

namespace App\Message;

final readonly class GenerateJokeEmbeddingMessage
{
    public function __construct(
        public int $jokeId,
    ) {
    }
}
