<?php

namespace App\Tests\Services;

use App\Ai\CompletionProviderInterface;
use App\Ai\EmbeddingProviderInterface;
use App\Ai\Search\SimilaritySearchInterface;
use App\Entity\Joke;
use App\Exception\AiServiceException;
use App\Repository\JokeRepository;
use App\Services\SemanticJokeSearch;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class SemanticJokeSearchTest extends TestCase
{
    public function testFallsBackToTextSearchWhenEmbeddingProviderThrows(): void
    {
        $embeddingProvider = $this->createMock(EmbeddingProviderInterface::class);
        $embeddingProvider->method('embed')->willThrowException(new AiServiceException('down'));

        $similaritySearch = $this->createMock(SimilaritySearchInterface::class);
        $similaritySearch->expects($this->never())->method('findSimilarJokeIds');

        $textResults = [(new Joke())->setJoke('A text-search result')];
        $jokeRepository = $this->createMock(JokeRepository::class);
        $jokeRepository->expects($this->once())->method('search')->with('debugger')->willReturn($textResults);

        $search = new SemanticJokeSearch(
            $embeddingProvider,
            $similaritySearch,
            $this->createMock(CompletionProviderInterface::class),
            $jokeRepository,
            new NullLogger(),
            rerankEnabled: false,
        );

        $result = $search->search('debugger');

        self::assertFalse($result->semantic);
        self::assertSame($textResults, $result->jokes);
    }

    public function testFallsBackToTextSearchWhenNoEmbeddingsAreIndexedYet(): void
    {
        $embeddingProvider = $this->createMock(EmbeddingProviderInterface::class);
        $embeddingProvider->method('embed')->willReturn([[0.1, 0.2]]);

        $similaritySearch = $this->createMock(SimilaritySearchInterface::class);
        $similaritySearch->method('findSimilarJokeIds')->willReturn([]);

        $jokeRepository = $this->createMock(JokeRepository::class);
        $jokeRepository->expects($this->once())->method('search')->willReturn([]);

        $search = new SemanticJokeSearch(
            $embeddingProvider,
            $similaritySearch,
            $this->createMock(CompletionProviderInterface::class),
            $jokeRepository,
            new NullLogger(),
            rerankEnabled: false,
        );

        $result = $search->search('anything');

        self::assertFalse($result->semantic);
    }

    public function testReturnsCosineRankedResultsWhenRerankIsDisabled(): void
    {
        $embeddingProvider = $this->createMock(EmbeddingProviderInterface::class);
        $embeddingProvider->method('embed')->willReturn([[0.1, 0.2]]);

        $similaritySearch = $this->createMock(SimilaritySearchInterface::class);
        $similaritySearch->method('findSimilarJokeIds')->willReturn([
            ['jokeId' => 2, 'score' => 0.9],
            ['jokeId' => 1, 'score' => 0.5],
        ]);

        $joke1 = (new Joke())->setJoke('Joke one');
        $joke2 = (new Joke())->setJoke('Joke two');
        $jokeRepository = $this->createMock(JokeRepository::class);
        $jokeRepository->expects($this->once())
            ->method('findByIdsPreservingOrder')
            ->with([2, 1])
            ->willReturn([$joke2, $joke1]);
        $jokeRepository->expects($this->never())->method('search');

        $completionProvider = $this->createMock(CompletionProviderInterface::class);
        $completionProvider->expects($this->never())->method('complete');

        $search = new SemanticJokeSearch(
            $embeddingProvider,
            $similaritySearch,
            $completionProvider,
            $jokeRepository,
            new NullLogger(),
            rerankEnabled: false,
        );

        $result = $search->search('debugger');

        self::assertTrue($result->semantic);
        self::assertSame([$joke2, $joke1], $result->jokes);
    }

    public function testUsesRerankedOrderWhenRerankSucceeds(): void
    {
        $embeddingProvider = $this->createMock(EmbeddingProviderInterface::class);
        $embeddingProvider->method('embed')->willReturn([[0.1, 0.2]]);

        $similaritySearch = $this->createMock(SimilaritySearchInterface::class);
        $similaritySearch->method('findSimilarJokeIds')->willReturn([
            ['jokeId' => 1, 'score' => 0.9],
            ['jokeId' => 2, 'score' => 0.5],
        ]);

        $joke1 = $this->jokeWithId(1, 'Joke one');
        $joke2 = $this->jokeWithId(2, 'Joke two');

        $byId = [1 => $joke1, 2 => $joke2];
        $jokeRepository = $this->createMock(JokeRepository::class);
        $jokeRepository->method('findByIdsPreservingOrder')->willReturnCallback(
            static fn (array $ids) => array_values(array_map(static fn (int $id) => $byId[$id], $ids)),
        );

        $completionProvider = $this->createMock(CompletionProviderInterface::class);
        $completionProvider->expects($this->once())->method('complete')->willReturn([
            'results' => [
                ['id' => 2, 'score' => 0.95],
                ['id' => 1, 'score' => 0.2],
            ],
        ]);

        $search = new SemanticJokeSearch(
            $embeddingProvider,
            $similaritySearch,
            $completionProvider,
            $jokeRepository,
            new NullLogger(),
            rerankEnabled: true,
        );

        $result = $search->search('debugger');

        self::assertTrue($result->semantic);
        self::assertSame([$joke2, $joke1], $result->jokes);
    }

    public function testKeepsCosineOrderWhenRerankFails(): void
    {
        $embeddingProvider = $this->createMock(EmbeddingProviderInterface::class);
        $embeddingProvider->method('embed')->willReturn([[0.1, 0.2]]);

        $similaritySearch = $this->createMock(SimilaritySearchInterface::class);
        $similaritySearch->method('findSimilarJokeIds')->willReturn([
            ['jokeId' => 1, 'score' => 0.9],
        ]);

        $joke1 = $this->jokeWithId(1, 'Joke one');
        $jokeRepository = $this->createMock(JokeRepository::class);
        $jokeRepository->method('findByIdsPreservingOrder')->willReturn([$joke1]);

        $completionProvider = $this->createMock(CompletionProviderInterface::class);
        $completionProvider->method('complete')->willThrowException(new AiServiceException('rerank down'));

        $search = new SemanticJokeSearch(
            $embeddingProvider,
            $similaritySearch,
            $completionProvider,
            $jokeRepository,
            new NullLogger(),
            rerankEnabled: true,
        );

        $result = $search->search('debugger');

        self::assertTrue($result->semantic);
        self::assertSame([$joke1], $result->jokes);
    }

    private function jokeWithId(int $id, string $text): Joke
    {
        $joke = (new Joke())->setJoke($text);
        (new \ReflectionProperty(Joke::class, 'id'))->setValue($joke, $id);

        return $joke;
    }
}
