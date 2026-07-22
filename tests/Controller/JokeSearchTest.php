<?php

namespace App\Tests\Controller;

use App\Ai\EmbeddingProviderInterface;
use App\Ai\Search\SimilaritySearchInterface;
use App\Entity\Joke;
use App\Entity\User;
use DAMA\DoctrineTestBundle\Doctrine\DBAL\StaticDriver;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class JokeSearchTest extends WebTestCase
{
    public function testSearchPageRedirectsAnonymousUsersToLogin(): void
    {
        $client = static::createClient();
        $client->request('GET', '/search?q=debugger');

        self::assertResponseRedirects('/login');
    }

    public function testSearchWithoutAQueryShowsNoResults(): void
    {
        $client = static::createClient();
        $client->loginUser($this->createUser());

        $client->request('GET', '/search');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Type something above to search');
    }

    public function testSearchFindsMatchingApprovedJokesOnly(): void
    {
        $client = static::createClient();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        // A single unique compound word, so BOOLEAN MODE (which matches any row containing the
        // word) can't accidentally also match the "non-matching" joke.
        $uniqueWord = 'debuggerinos'.bin2hex(random_bytes(4));
        $matching = (new Joke())->setJoke("Chuck Norris fixes {$uniqueWord} by staring at it.");
        $nonMatching = (new Joke())->setJoke('Chuck Norris counts to infinity twice.');
        $pendingMatch = (new Joke())->setJoke("Chuck Norris also knows {$uniqueWord} but nobody approved it yet.")->setApproved(false);
        $user = $this->createUser();
        $entityManager->persist($matching);
        $entityManager->persist($nonMatching);
        $entityManager->persist($pendingMatch);
        $entityManager->flush();

        // InnoDB's FULLTEXT index only reflects committed data - MATCH() AGAINST() run from
        // within this test's own (never-committed-until-rollback) DAMA transaction would find
        // nothing. Commit for real, run the assertions, then explicitly clean up afterwards
        // since DAMA's usual rollback-based isolation no longer applies to these rows.
        $jokeIds = [$matching->getId(), $nonMatching->getId(), $pendingMatch->getId()];
        $userId = $user->getId();

        StaticDriver::commit();
        StaticDriver::beginTransaction();

        try {
            $client->loginUser($user);
            $crawler = $client->request('GET', '/search?q='.$uniqueWord);

            self::assertResponseIsSuccessful();
            $text = $crawler->filter('body')->text();
            self::assertStringContainsString("Chuck Norris fixes {$uniqueWord}", $text);
            self::assertStringNotContainsString('counts to infinity', $text);
            self::assertStringNotContainsString('nobody approved it yet', $text);
        } finally {
            // The kernel reboots per-request, so re-fetch a live EntityManager rather than
            // reusing $entityManager - the entities it originally tracked may now be detached.
            $freshEntityManager = static::getContainer()->get(EntityManagerInterface::class);
            $freshEntityManager->createQuery('DELETE FROM App\Entity\Joke j WHERE j.id IN (:ids)')
                ->setParameter('ids', $jokeIds)
                ->execute();
            $freshEntityManager->createQuery('DELETE FROM App\Entity\User u WHERE u.id = :id')
                ->setParameter('id', $userId)
                ->execute();
            StaticDriver::commit();
            StaticDriver::beginTransaction();
        }
    }

    public function testSearchWithNoMatchesShowsAMessage(): void
    {
        $client = static::createClient();
        $client->loginUser($this->createUser());

        $client->request('GET', '/search?q='.bin2hex(random_bytes(8)));

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'No jokes found');
    }

    public function testSearchFallsBackToTextSearchWithoutShowingTheSemanticBadge(): void
    {
        // The test env's AI_PROVIDER=null makes every AI call throw, so this exercises the
        // same fallback path a real AI-service outage would.
        $client = static::createClient();
        $container = static::getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);

        $uniqueWord = 'fallbacksearch'.bin2hex(random_bytes(4));
        $joke = (new Joke())->setJoke("Chuck Norris debugs {$uniqueWord} instantly.");
        $entityManager->persist($joke);
        $entityManager->flush();
        $jokeId = $joke->getId();

        StaticDriver::commit();
        StaticDriver::beginTransaction();

        try {
            $client->loginUser($this->createUser());
            $crawler = $client->request('GET', '/search?q='.$uniqueWord);

            self::assertResponseIsSuccessful();
            self::assertStringContainsString($uniqueWord, $crawler->filter('body')->text());
            self::assertSelectorNotExists('span:contains("semantisch")');
        } finally {
            $freshEntityManager = static::getContainer()->get(EntityManagerInterface::class);
            $freshEntityManager->createQuery('DELETE FROM App\Entity\Joke j WHERE j.id = :id')
                ->setParameter('id', $jokeId)
                ->execute();
            StaticDriver::commit();
            StaticDriver::beginTransaction();
        }
    }

    public function testSearchShowsTheSemanticBadgeWhenTheAiPathSucceeds(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);

        $joke = (new Joke())->setJoke('A joke findable only via semantic search');
        $entityManager->persist($joke);
        $entityManager->flush();

        $embeddingProvider = $this->createMock(EmbeddingProviderInterface::class);
        $embeddingProvider->method('embed')->willReturn([[0.1, 0.2]]);
        $container->set(EmbeddingProviderInterface::class, $embeddingProvider);

        $similaritySearch = $this->createMock(SimilaritySearchInterface::class);
        $similaritySearch->method('findSimilarJokeIds')->willReturn([
            ['jokeId' => $joke->getId(), 'score' => 0.99],
        ]);
        $container->set(SimilaritySearchInterface::class, $similaritySearch);

        $client->loginUser($this->createUser());
        $crawler = $client->request('GET', '/search?q=anything');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('A joke findable only via semantic search', $crawler->filter('body')->text());
        self::assertSelectorExists('span:contains("semantisch")');
    }

    private function createUser(): User
    {
        $container = static::getContainer();

        $user = new User();
        $user->setEmail(sprintf('searcher-%s@example.com', bin2hex(random_bytes(4))));
        $user->setPassword($container->get(UserPasswordHasherInterface::class)->hashPassword($user, 'irrelevant'));

        $entityManager = $container->get(EntityManagerInterface::class);
        $entityManager->persist($user);
        $entityManager->flush();

        return $user;
    }
}
