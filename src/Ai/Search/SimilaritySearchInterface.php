<?php

namespace App\Ai\Search;

interface SimilaritySearchInterface
{
    /**
     * @param float[] $queryVector
     *
     * @return array<int, array{jokeId: int, score: float}> most similar first
     */
    public function findSimilarJokeIds(array $queryVector, int $limit): array;
}
