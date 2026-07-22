<?php

namespace App\MessageHandler;

use App\Ai\EmbeddingProviderInterface;
use App\Entity\JokeEmbedding;
use App\Exception\AiServiceException;
use App\Message\GenerateJokeEmbeddingMessage;
use App\Repository\JokeEmbeddingRepository;
use App\Repository\JokeRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Runs on the async transport (config/packages/messenger.yaml) so the request that triggered it
 * (saving or approving a joke) never waits on the AI service. A failure here is swallowed rather
 * than retried by Messenger - the joke simply stays without an embedding, exactly as if it were
 * still queued, and app:embeddings:backfill will pick it up on its next run.
 */
#[AsMessageHandler]
final readonly class GenerateJokeEmbeddingMessageHandler
{
    public function __construct(
        private JokeRepository $jokeRepository,
        private JokeEmbeddingRepository $jokeEmbeddingRepository,
        private EmbeddingProviderInterface $embeddingProvider,
        #[Autowire(service: 'monolog.logger.ai')]
        private LoggerInterface $logger,
        #[Autowire('%env(EMBEDDING_MODEL)%')]
        private string $embeddingModel,
    ) {
    }

    public function __invoke(GenerateJokeEmbeddingMessage $message): void
    {
        $joke = $this->jokeRepository->find($message->jokeId);
        if ($joke === null || !$joke->isApproved()) {
            return;
        }

        if ($this->jokeEmbeddingRepository->findOneByJoke($joke) !== null) {
            return;
        }

        try {
            $vectors = $this->embeddingProvider->embed([$joke->getJoke()]);
        } catch (AiServiceException $exception) {
            $this->logger->warning('Could not generate embedding for joke.', [
                'jokeId' => $joke->getId(),
                'error' => $exception->getMessage(),
            ]);

            return;
        }

        $this->jokeEmbeddingRepository->save(new JokeEmbedding($joke, $this->embeddingModel, $vectors[0]));
    }
}
