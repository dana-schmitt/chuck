<?php

namespace App\Services;

final readonly class FetchedJoke
{
    /**
     * @param string[] $categories
     */
    public function __construct(
        public string $text,
        public array $categories = [],
    ) {
    }
}
