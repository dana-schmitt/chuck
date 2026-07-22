<?php

namespace App\Ai\Search;

use App\Repository\JokeEmbeddingRepository;

/**
 * Compares the query vector against every stored joke embedding in PHP. Fine at this app's
 * scale (a handful of thousand jokes at most); kept behind SimilaritySearchInterface so a real
 * vector database could be swapped in later without touching any caller.
 */
final readonly class BruteForceCosineSimilaritySearch implements SimilaritySearchInterface
{
    public function __construct(
        private JokeEmbeddingRepository $jokeEmbeddingRepository,
    ) {
    }

    public function findSimilarJokeIds(array $queryVector, int $limit): array
    {
        $vectorsByJokeId = $this->jokeEmbeddingRepository->findAllVectorsByJokeId();

        $scores = [];
        foreach ($vectorsByJokeId as $jokeId => $vector) {
            $scores[$jokeId] = self::cosineSimilarity($queryVector, $vector);
        }

        arsort($scores);

        $results = [];
        foreach (\array_slice($scores, 0, $limit, true) as $jokeId => $score) {
            $results[] = ['jokeId' => $jokeId, 'score' => $score];
        }

        return $results;
    }

    /**
     * @param float[] $a
     * @param float[] $b
     */
    public static function cosineSimilarity(array $a, array $b): float
    {
        $dotProduct = 0.0;
        $normA = 0.0;
        $normB = 0.0;

        $length = min(\count($a), \count($b));
        for ($i = 0; $i < $length; ++$i) {
            $dotProduct += $a[$i] * $b[$i];
            $normA += $a[$i] ** 2;
            $normB += $b[$i] ** 2;
        }

        if ($normA <= 0.0 || $normB <= 0.0) {
            return 0.0;
        }

        return $dotProduct / (sqrt($normA) * sqrt($normB));
    }
}
