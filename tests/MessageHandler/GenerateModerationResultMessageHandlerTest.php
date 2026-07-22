<?php

namespace App\Tests\MessageHandler;

use App\Ai\CompletionProviderInterface;
use App\Ai\EmbeddingProviderInterface;
use App\Ai\Search\SimilaritySearchInterface;
use App\Entity\Joke;
use App\Entity\ModerationResult;
use App\Enum\ModerationRecommendation;
use App\Exception\AiServiceException;
use App\Message\GenerateModerationResultMessage;
use App\MessageHandler\GenerateModerationResultMessageHandler;
use App\Repository\JokeRepository;
use App\Repository\ModerationResultRepository;
use App\Services\JokeModerationAnalyzer;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class GenerateModerationResultMessageHandlerTest extends TestCase
{
    public function testAnalyzesAPendingJokeAndSavesTheResult(): void
    {
        $joke = (new Joke())->setJoke('A pending submission')->setApproved(false);

        $jokeRepository = $this->createMock(JokeRepository::class);
        $jokeRepository->method('find')->with(7)->willReturn($joke);

        $moderationResultRepository = $this->createMock(ModerationResultRepository::class);
        $moderationResultRepository->method('findOneByJoke')->with($joke)->willReturn(null);
        $moderationResultRepository->expects($this->once())->method('save')->with(
            $this->callback(static fn (ModerationResult $result) => $result->getJoke() === $joke
                && $result->getRecommendation() === ModerationRecommendation::Approve),
        );

        $analyzer = $this->analyzerReturning([
            'recommendation' => 'approve',
            'confidence' => 0.8,
            'reasons' => [],
            'flags' => [],
        ]);

        $handler = new GenerateModerationResultMessageHandler($jokeRepository, $moderationResultRepository, $analyzer, new NullLogger());

        $handler(new GenerateModerationResultMessage(7));
    }

    public function testSkipsAJokeThatIsAlreadyApproved(): void
    {
        $joke = (new Joke())->setJoke('Already approved')->setApproved(true);

        $jokeRepository = $this->createMock(JokeRepository::class);
        $jokeRepository->method('find')->willReturn($joke);

        $moderationResultRepository = $this->createMock(ModerationResultRepository::class);
        $moderationResultRepository->expects($this->never())->method('findOneByJoke');

        $completionProvider = $this->createMock(CompletionProviderInterface::class);
        $completionProvider->expects($this->never())->method('complete');
        $analyzer = new JokeModerationAnalyzer(
            $this->createMock(EmbeddingProviderInterface::class),
            $this->createMock(SimilaritySearchInterface::class),
            $completionProvider,
            $jokeRepository,
        );

        $handler = new GenerateModerationResultMessageHandler($jokeRepository, $moderationResultRepository, $analyzer, new NullLogger());

        $handler(new GenerateModerationResultMessage(1));
    }

    public function testSkipsAJokeThatAlreadyHasAModerationResult(): void
    {
        $joke = (new Joke())->setJoke('Already analyzed')->setApproved(false);

        $jokeRepository = $this->createMock(JokeRepository::class);
        $jokeRepository->method('find')->willReturn($joke);

        $moderationResultRepository = $this->createMock(ModerationResultRepository::class);
        $moderationResultRepository->method('findOneByJoke')->willReturn(
            new ModerationResult($joke, ModerationRecommendation::Unsure, 0.5, [], []),
        );
        $moderationResultRepository->expects($this->never())->method('save');

        $completionProvider = $this->createMock(CompletionProviderInterface::class);
        $completionProvider->expects($this->never())->method('complete');
        $analyzer = new JokeModerationAnalyzer(
            $this->createMock(EmbeddingProviderInterface::class),
            $this->createMock(SimilaritySearchInterface::class),
            $completionProvider,
            $jokeRepository,
        );

        $handler = new GenerateModerationResultMessageHandler($jokeRepository, $moderationResultRepository, $analyzer, new NullLogger());

        $handler(new GenerateModerationResultMessage(1));
    }

    public function testSwallowsAiServiceFailuresRatherThanThrowing(): void
    {
        $joke = (new Joke())->setJoke('Will fail to analyze')->setApproved(false);

        $jokeRepository = $this->createMock(JokeRepository::class);
        $jokeRepository->method('find')->willReturn($joke);

        $moderationResultRepository = $this->createMock(ModerationResultRepository::class);
        $moderationResultRepository->method('findOneByJoke')->willReturn(null);
        $moderationResultRepository->expects($this->never())->method('save');

        $embeddingProvider = $this->createMock(EmbeddingProviderInterface::class);
        $embeddingProvider->method('embed')->willThrowException(new AiServiceException('down'));
        $analyzer = new JokeModerationAnalyzer(
            $embeddingProvider,
            $this->createMock(SimilaritySearchInterface::class),
            $this->createMock(CompletionProviderInterface::class),
            $jokeRepository,
        );

        $handler = new GenerateModerationResultMessageHandler($jokeRepository, $moderationResultRepository, $analyzer, new NullLogger());

        $handler(new GenerateModerationResultMessage(1));
    }

    private function analyzerReturning(array $completionResult): JokeModerationAnalyzer
    {
        $embeddingProvider = $this->createMock(EmbeddingProviderInterface::class);
        $embeddingProvider->method('embed')->willReturn([[0.1, 0.2]]);

        $similaritySearch = $this->createMock(SimilaritySearchInterface::class);
        $similaritySearch->method('findSimilarJokeIds')->willReturn([]);

        $completionProvider = $this->createMock(CompletionProviderInterface::class);
        $completionProvider->method('complete')->willReturn($completionResult);

        return new JokeModerationAnalyzer(
            $embeddingProvider,
            $similaritySearch,
            $completionProvider,
            $this->createMock(JokeRepository::class),
        );
    }
}
