<?php

namespace App\Command;

use App\Ai\EmbeddingProviderInterface;
use App\Entity\JokeEmbedding;
use App\Exception\AiServiceException;
use App\Repository\JokeEmbeddingRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(
    name: 'app:embeddings:backfill',
    description: 'Generates embeddings for approved jokes that do not have one yet.',
)]
class EmbeddingsBackfillCommand extends Command
{
    // Kept well under ai-service's own 100-per-OpenAI-call batching, mainly to keep progress
    // bar updates and any single failure's blast radius small.
    private const BATCH_SIZE = 20;

    public function __construct(
        private readonly JokeEmbeddingRepository $jokeEmbeddingRepository,
        private readonly EmbeddingProviderInterface $embeddingProvider,
        #[Autowire('%env(EMBEDDING_MODEL)%')]
        private readonly string $embeddingModel,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Maximum number of jokes to process');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $limit = $input->getOption('limit');
        $jokes = $this->jokeEmbeddingRepository->findApprovedJokesWithoutEmbedding($limit !== null ? (int) $limit : null);

        if ($jokes === []) {
            $io->success('Nothing to do - every approved joke already has an embedding.');

            return Command::SUCCESS;
        }

        $io->progressStart(\count($jokes));

        $processed = 0;
        foreach (array_chunk($jokes, self::BATCH_SIZE) as $batch) {
            try {
                $vectors = $this->embeddingProvider->embed(array_map(
                    static fn ($joke) => $joke->getJoke(),
                    $batch,
                ));
            } catch (AiServiceException $exception) {
                $io->progressFinish();
                $io->error(\sprintf(
                    'Stopping: the AI service failed after embedding %d/%d jokes (%s).',
                    $processed,
                    \count($jokes),
                    $exception->getMessage(),
                ));

                return Command::FAILURE;
            }

            foreach ($batch as $i => $joke) {
                // findOneByJoke() guards against a message handler having embedded this exact
                // joke concurrently between when we selected it and now.
                if ($this->jokeEmbeddingRepository->findOneByJoke($joke) === null) {
                    $this->jokeEmbeddingRepository->save(new JokeEmbedding($joke, $this->embeddingModel, $vectors[$i]));
                }
                ++$processed;
                $io->progressAdvance();
            }
        }

        $io->progressFinish();
        $io->success(\sprintf('Embedded %d joke(s).', $processed));

        return Command::SUCCESS;
    }
}
