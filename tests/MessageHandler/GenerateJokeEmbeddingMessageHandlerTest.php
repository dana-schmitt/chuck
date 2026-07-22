<?php

namespace App\Tests\MessageHandler;

use App\Ai\EmbeddingProviderInterface;
use App\Entity\Joke;
use App\Exception\AiServiceException;
use App\Message\GenerateJokeEmbeddingMessage;
use App\MessageHandler\GenerateJokeEmbeddingMessageHandler;
use App\Repository\JokeEmbeddingRepository;
use App\Repository\JokeRepository;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class GenerateJokeEmbeddingMessageHandlerTest extends TestCase
{
    public function testGeneratesAndSavesAnEmbeddingForAnApprovedJoke(): void
    {
        $joke = (new Joke())->setJoke('A joke to embed')->setApproved(true);

        $jokeRepository = $this->createMock(JokeRepository::class);
        $jokeRepository->method('find')->with(42)->willReturn($joke);

        $jokeEmbeddingRepository = $this->createMock(JokeEmbeddingRepository::class);
        $jokeEmbeddingRepository->method('findOneByJoke')->with($joke)->willReturn(null);
        $jokeEmbeddingRepository->expects($this->once())->method('save')->with(
            $this->callback(static fn ($embedding) => $embedding->getJoke() === $joke && $embedding->getVector() === [0.1, 0.2]),
        );

        $embeddingProvider = $this->createMock(EmbeddingProviderInterface::class);
        $embeddingProvider->expects($this->once())->method('embed')->with(['A joke to embed'])->willReturn([[0.1, 0.2]]);

        $handler = new GenerateJokeEmbeddingMessageHandler(
            $jokeRepository,
            $jokeEmbeddingRepository,
            $embeddingProvider,
            new NullLogger(),
            'text-embedding-3-small',
        );

        $handler(new GenerateJokeEmbeddingMessage(42));
    }

    public function testSkipsAJokeThatAlreadyHasAnEmbedding(): void
    {
        $joke = (new Joke())->setJoke('Already embedded')->setApproved(true);

        $jokeRepository = $this->createMock(JokeRepository::class);
        $jokeRepository->method('find')->willReturn($joke);

        $jokeEmbeddingRepository = $this->createMock(JokeEmbeddingRepository::class);
        $jokeEmbeddingRepository->method('findOneByJoke')->willReturn(
            new \App\Entity\JokeEmbedding($joke, 'text-embedding-3-small', [0.1]),
        );
        $jokeEmbeddingRepository->expects($this->never())->method('save');

        $embeddingProvider = $this->createMock(EmbeddingProviderInterface::class);
        $embeddingProvider->expects($this->never())->method('embed');

        $handler = new GenerateJokeEmbeddingMessageHandler(
            $jokeRepository,
            $jokeEmbeddingRepository,
            $embeddingProvider,
            new NullLogger(),
            'text-embedding-3-small',
        );

        $handler(new GenerateJokeEmbeddingMessage(1));
    }

    public function testDoesNothingWhenTheJokeNoLongerExists(): void
    {
        $jokeRepository = $this->createMock(JokeRepository::class);
        $jokeRepository->method('find')->willReturn(null);

        $jokeEmbeddingRepository = $this->createMock(JokeEmbeddingRepository::class);
        $jokeEmbeddingRepository->expects($this->never())->method('findOneByJoke');

        $embeddingProvider = $this->createMock(EmbeddingProviderInterface::class);
        $embeddingProvider->expects($this->never())->method('embed');

        $handler = new GenerateJokeEmbeddingMessageHandler(
            $jokeRepository,
            $jokeEmbeddingRepository,
            $embeddingProvider,
            new NullLogger(),
            'text-embedding-3-small',
        );

        $handler(new GenerateJokeEmbeddingMessage(999));
    }

    public function testSwallowsAiServiceFailuresRatherThanThrowing(): void
    {
        $joke = (new Joke())->setJoke('Will fail to embed')->setApproved(true);

        $jokeRepository = $this->createMock(JokeRepository::class);
        $jokeRepository->method('find')->willReturn($joke);

        $jokeEmbeddingRepository = $this->createMock(JokeEmbeddingRepository::class);
        $jokeEmbeddingRepository->method('findOneByJoke')->willReturn(null);
        $jokeEmbeddingRepository->expects($this->never())->method('save');

        $embeddingProvider = $this->createMock(EmbeddingProviderInterface::class);
        $embeddingProvider->method('embed')->willThrowException(new AiServiceException('down'));

        $handler = new GenerateJokeEmbeddingMessageHandler(
            $jokeRepository,
            $jokeEmbeddingRepository,
            $embeddingProvider,
            new NullLogger(),
            'text-embedding-3-small',
        );

        // No exception should escape - the joke just stays unembedded for now.
        $handler(new GenerateJokeEmbeddingMessage(1));
    }
}
