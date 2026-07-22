<?php

namespace App\Services;

use App\Entity\Joke;

final readonly class SemanticSearchResult
{
    /**
     * @param Joke[] $jokes
     */
    public function __construct(
        public array $jokes,
        public bool $semantic,
    ) {
    }
}
