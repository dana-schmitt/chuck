<?php

namespace App\Tests\Ai\Search;

use App\Ai\Search\BruteForceCosineSimilaritySearch;
use App\Repository\JokeEmbeddingRepository;
use PHPUnit\Framework\TestCase;

class BruteForceCosineSimilaritySearchTest extends TestCase
{
    public function testIdenticalVectorsHaveASimilarityOfOne(): void
    {
        self::assertEqualsWithDelta(
            1.0,
            BruteForceCosineSimilaritySearch::cosineSimilarity([1.0, 2.0, 3.0], [1.0, 2.0, 3.0]),
            0.0001,
        );
    }

    public function testOrthogonalVectorsHaveASimilarityOfZero(): void
    {
        self::assertEqualsWithDelta(
            0.0,
            BruteForceCosineSimilaritySearch::cosineSimilarity([1.0, 0.0], [0.0, 1.0]),
            0.0001,
        );
    }

    public function testOppositeVectorsHaveASimilarityOfMinusOne(): void
    {
        self::assertEqualsWithDelta(
            -1.0,
            BruteForceCosineSimilaritySearch::cosineSimilarity([1.0, 2.0], [-1.0, -2.0]),
            0.0001,
        );
    }

    public function testZeroVectorHasZeroSimilarityRatherThanDividingByZero(): void
    {
        self::assertSame(0.0, BruteForceCosineSimilaritySearch::cosineSimilarity([0.0, 0.0], [1.0, 2.0]));
    }

    public function testFindSimilarJokeIdsRanksMostSimilarFirstAndRespectsLimit(): void
    {
        $repository = $this->createMock(JokeEmbeddingRepository::class);
        $repository->method('findAllVectorsByJokeId')->willReturn([
            1 => [1.0, 0.0], // identical to the query
            2 => [0.0, 1.0], // orthogonal
            3 => [0.9, 0.1], // close to the query
        ]);

        $search = new BruteForceCosineSimilaritySearch($repository);

        $results = $search->findSimilarJokeIds([1.0, 0.0], limit: 2);

        self::assertCount(2, $results);
        self::assertSame(1, $results[0]['jokeId']);
        self::assertSame(3, $results[1]['jokeId']);
        self::assertEqualsWithDelta(1.0, $results[0]['score'], 0.0001);
    }
}
