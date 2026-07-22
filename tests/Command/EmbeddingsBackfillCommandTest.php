<?php

namespace App\Tests\Command;

use App\Ai\EmbeddingProviderInterface;
use App\Entity\Joke;
use App\Entity\JokeEmbedding;
use App\Repository\JokeEmbeddingRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

class EmbeddingsBackfillCommandTest extends KernelTestCase
{
    public function testEmbedsOnlyApprovedJokesWithoutAnExistingEmbedding(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);

        $suffix = bin2hex(random_bytes(4));
        $needsEmbedding = (new Joke())->setJoke("Needs an embedding {$suffix}")->setApproved(true);
        $alreadyEmbedded = (new Joke())->setJoke("Already embedded {$suffix}")->setApproved(true);
        $pending = (new Joke())->setJoke("Not approved yet {$suffix}")->setApproved(false);
        $entityManager->persist($needsEmbedding);
        $entityManager->persist($alreadyEmbedded);
        $entityManager->persist($pending);
        $entityManager->flush();

        $entityManager->persist(new JokeEmbedding($alreadyEmbedded, 'text-embedding-3-small', [0.1, 0.2]));
        $entityManager->flush();

        $embeddingProvider = $this->createMock(EmbeddingProviderInterface::class);
        $embeddingProvider->expects($this->once())
            ->method('embed')
            ->with([$needsEmbedding->getJoke()])
            ->willReturn([[0.5, 0.6]]);
        $container->set(EmbeddingProviderInterface::class, $embeddingProvider);

        $application = new Application(self::$kernel);
        $command = $application->find('app:embeddings:backfill');
        $tester = new CommandTester($command);
        $tester->execute([]);

        $tester->assertCommandIsSuccessful();

        $jokeEmbeddingRepository = $container->get(JokeEmbeddingRepository::class);
        self::assertNotNull($jokeEmbeddingRepository->findOneByJoke($needsEmbedding));
        self::assertNull($jokeEmbeddingRepository->findOneByJoke($pending));
    }

    public function testLimitOptionCapsHowManyJokesAreProcessed(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);

        $suffix = bin2hex(random_bytes(4));
        $jokeA = (new Joke())->setJoke("Limit test A {$suffix}")->setApproved(true);
        $jokeB = (new Joke())->setJoke("Limit test B {$suffix}")->setApproved(true);
        $entityManager->persist($jokeA);
        $entityManager->persist($jokeB);
        $entityManager->flush();

        $embeddingProvider = $this->createMock(EmbeddingProviderInterface::class);
        $embeddingProvider->expects($this->once())->method('embed')->willReturn([[0.1]]);
        $container->set(EmbeddingProviderInterface::class, $embeddingProvider);

        $application = new Application(self::$kernel);
        $command = $application->find('app:embeddings:backfill');
        $tester = new CommandTester($command);
        $tester->execute(['--limit' => 1]);

        $tester->assertCommandIsSuccessful();

        $jokeEmbeddingRepository = $container->get(JokeEmbeddingRepository::class);
        $embeddedCount = ($jokeEmbeddingRepository->findOneByJoke($jokeA) !== null ? 1 : 0)
            + ($jokeEmbeddingRepository->findOneByJoke($jokeB) !== null ? 1 : 0);
        self::assertSame(1, $embeddedCount);
    }

    public function testReportsSuccessWithNothingToDoWhenEverythingIsAlreadyEmbedded(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        $embeddingProvider = $this->createMock(EmbeddingProviderInterface::class);
        $embeddingProvider->expects($this->never())->method('embed');
        $container->set(EmbeddingProviderInterface::class, $embeddingProvider);

        $application = new Application(self::$kernel);
        $command = $application->find('app:embeddings:backfill');
        $tester = new CommandTester($command);
        $tester->execute(['--limit' => 0]);

        $tester->assertCommandIsSuccessful();
    }
}
