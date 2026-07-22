<?php

namespace App\MessageHandler;

use App\Exception\AiServiceException;
use App\Message\GenerateModerationResultMessage;
use App\Repository\JokeRepository;
use App\Repository\ModerationResultRepository;
use App\Services\JokeModerationAnalyzer;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Runs on the async transport so submitting a joke never waits on the AI service. Like
 * GenerateJokeEmbeddingMessageHandler, a failure here is swallowed rather than retried - the
 * admin just sees "no AI analysis available" and reviews the joke manually, exactly as if this
 * feature didn't exist. There's no backfill command for this one: unlike embeddings, a missed
 * moderation result isn't worth silently regenerating later once an admin may have already
 * acted on the submission without it.
 */
#[AsMessageHandler]
final readonly class GenerateModerationResultMessageHandler
{
    public function __construct(
        private JokeRepository $jokeRepository,
        private ModerationResultRepository $moderationResultRepository,
        private JokeModerationAnalyzer $analyzer,
        #[Autowire(service: 'monolog.logger.ai')]
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(GenerateModerationResultMessage $message): void
    {
        $joke = $this->jokeRepository->find($message->jokeId);
        if ($joke === null || $joke->isApproved()) {
            return;
        }

        if ($this->moderationResultRepository->findOneByJoke($joke) !== null) {
            return;
        }

        try {
            $result = $this->analyzer->analyze($joke);
        } catch (AiServiceException $exception) {
            $this->logger->warning('Could not generate a moderation result for a submitted joke.', [
                'jokeId' => $joke->getId(),
                'error' => $exception->getMessage(),
            ]);

            return;
        }

        $this->moderationResultRepository->save($result);
    }
}
