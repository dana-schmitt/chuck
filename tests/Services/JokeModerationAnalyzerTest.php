<?php

namespace App\Tests\Services;

use App\Ai\CompletionProviderInterface;
use App\Ai\EmbeddingProviderInterface;
use App\Ai\Search\SimilaritySearchInterface;
use App\Entity\Joke;
use App\Enum\ModerationFlag;
use App\Enum\ModerationRecommendation;
use App\Exception\AiServiceException;
use App\Repository\JokeRepository;
use App\Services\JokeModerationAnalyzer;
use PHPUnit\Framework\TestCase;

class JokeModerationAnalyzerTest extends TestCase
{
    public function testMapsTheStructuredOutputOntoAModerationResult(): void
    {
        $joke = $this->jokeWithId(1, 'A brand new joke');

        $embeddingProvider = $this->createMock(EmbeddingProviderInterface::class);
        $embeddingProvider->method('embed')->willReturn([[0.1, 0.2]]);

        $similaritySearch = $this->createMock(SimilaritySearchInterface::class);
        $similaritySearch->method('findSimilarJokeIds')->willReturn([]);

        $completionProvider = $this->createMock(CompletionProviderInterface::class);
        $completionProvider->expects($this->once())->method('complete')->willReturn([
            'recommendation' => 'approve',
            'confidence' => 0.87,
            'reasons' => ['Reads like a genuine Chuck Norris joke.'],
            'flags' => [],
        ]);

        $jokeRepository = $this->createMock(JokeRepository::class);

        $analyzer = new JokeModerationAnalyzer($embeddingProvider, $similaritySearch, $completionProvider, $jokeRepository);

        $result = $analyzer->analyze($joke);

        self::assertSame($joke, $result->getJoke());
        self::assertSame(ModerationRecommendation::Approve, $result->getRecommendation());
        self::assertEqualsWithDelta(0.87, $result->getConfidence(), 0.0001);
        self::assertSame(['Reads like a genuine Chuck Norris joke.'], $result->getReasons());
        self::assertSame([], $result->getFlags());
        self::assertNull($result->getDuplicateOf());
    }

    public function testMapsFlagsAndFallsBackToUnsureForAnUnrecognizedRecommendation(): void
    {
        $joke = $this->jokeWithId(1, 'Questionable content');

        $embeddingProvider = $this->createMock(EmbeddingProviderInterface::class);
        $embeddingProvider->method('embed')->willReturn([[0.1]]);

        $similaritySearch = $this->createMock(SimilaritySearchInterface::class);
        $similaritySearch->method('findSimilarJokeIds')->willReturn([]);

        $completionProvider = $this->createMock(CompletionProviderInterface::class);
        $completionProvider->method('complete')->willReturn([
            'recommendation' => 'maybe-approve', // not a real enum value - the fake provider or a
                                                  // misbehaving model could send something like this
            'confidence' => 0.5,
            'reasons' => [],
            'flags' => ['offensive', 'not_a_joke', 'something_unknown'],
        ]);

        $analyzer = new JokeModerationAnalyzer(
            $embeddingProvider,
            $similaritySearch,
            $completionProvider,
            $this->createMock(JokeRepository::class),
        );

        $result = $analyzer->analyze($joke);

        self::assertSame(ModerationRecommendation::Unsure, $result->getRecommendation());
        self::assertSame([ModerationFlag::Offensive, ModerationFlag::NotAJoke], $result->getFlags());
    }

    public function testFindsADuplicateAboveTheSimilarityThreshold(): void
    {
        $joke = $this->jokeWithId(5, 'This joke already exists');
        $existingJoke = $this->jokeWithId(3, 'The original joke');

        $embeddingProvider = $this->createMock(EmbeddingProviderInterface::class);
        $embeddingProvider->method('embed')->willReturn([[0.9, 0.1]]);

        $similaritySearch = $this->createMock(SimilaritySearchInterface::class);
        $similaritySearch->method('findSimilarJokeIds')->willReturn([
            ['jokeId' => 3, 'score' => 0.97],
            ['jokeId' => 9, 'score' => 0.6],
        ]);

        $jokeRepository = $this->createMock(JokeRepository::class);
        $jokeRepository->method('find')->with(3)->willReturn($existingJoke);

        $completionProvider = $this->createMock(CompletionProviderInterface::class);
        $completionProvider->method('complete')->willReturn([
            'recommendation' => 'unsure',
            'confidence' => 0.3,
            'reasons' => [],
            'flags' => [],
        ]);

        $analyzer = new JokeModerationAnalyzer($embeddingProvider, $similaritySearch, $completionProvider, $jokeRepository);

        $result = $analyzer->analyze($joke);

        self::assertSame($existingJoke, $result->getDuplicateOf());
    }

    public function testDoesNotFlagAWeakMatchBelowTheSimilarityThresholdAsADuplicate(): void
    {
        $joke = $this->jokeWithId(5, 'A fairly original joke');

        $embeddingProvider = $this->createMock(EmbeddingProviderInterface::class);
        $embeddingProvider->method('embed')->willReturn([[0.5, 0.5]]);

        $similaritySearch = $this->createMock(SimilaritySearchInterface::class);
        $similaritySearch->method('findSimilarJokeIds')->willReturn([
            ['jokeId' => 3, 'score' => 0.7],
        ]);

        $jokeRepository = $this->createMock(JokeRepository::class);
        $jokeRepository->expects($this->never())->method('find');

        $completionProvider = $this->createMock(CompletionProviderInterface::class);
        $completionProvider->method('complete')->willReturn([
            'recommendation' => 'approve',
            'confidence' => 0.9,
            'reasons' => [],
            'flags' => [],
        ]);

        $analyzer = new JokeModerationAnalyzer($embeddingProvider, $similaritySearch, $completionProvider, $jokeRepository);

        $result = $analyzer->analyze($joke);

        self::assertNull($result->getDuplicateOf());
    }

    public function testPropagatesAiServiceExceptionsFromTheCompletionCall(): void
    {
        $joke = $this->jokeWithId(1, 'Some joke');

        $embeddingProvider = $this->createMock(EmbeddingProviderInterface::class);
        $embeddingProvider->method('embed')->willReturn([[0.1]]);

        $similaritySearch = $this->createMock(SimilaritySearchInterface::class);
        $similaritySearch->method('findSimilarJokeIds')->willReturn([]);

        $completionProvider = $this->createMock(CompletionProviderInterface::class);
        $completionProvider->method('complete')->willThrowException(new AiServiceException('down'));

        $analyzer = new JokeModerationAnalyzer(
            $embeddingProvider,
            $similaritySearch,
            $completionProvider,
            $this->createMock(JokeRepository::class),
        );

        $this->expectException(AiServiceException::class);
        $analyzer->analyze($joke);
    }

    public function testPropagatesAiServiceExceptionsFromTheEmbeddingCall(): void
    {
        $joke = $this->jokeWithId(1, 'Some joke');

        $embeddingProvider = $this->createMock(EmbeddingProviderInterface::class);
        $embeddingProvider->method('embed')->willThrowException(new AiServiceException('down'));

        $completionProvider = $this->createMock(CompletionProviderInterface::class);
        $completionProvider->expects($this->never())->method('complete');

        $analyzer = new JokeModerationAnalyzer(
            $embeddingProvider,
            $this->createMock(SimilaritySearchInterface::class),
            $completionProvider,
            $this->createMock(JokeRepository::class),
        );

        $this->expectException(AiServiceException::class);
        $analyzer->analyze($joke);
    }

    private function jokeWithId(int $id, string $text): Joke
    {
        $joke = (new Joke())->setJoke($text);
        (new \ReflectionProperty(Joke::class, 'id'))->setValue($joke, $id);

        return $joke;
    }
}
