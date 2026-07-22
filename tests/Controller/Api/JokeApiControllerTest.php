<?php

namespace App\Tests\Controller\Api;

use App\Entity\Joke;
use App\Entity\JokeLike;
use App\Entity\User;
use DAMA\DoctrineTestBundle\Doctrine\DBAL\StaticDriver;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class JokeApiControllerTest extends WebTestCase
{
    public function testApiRequiresAuthentication(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/jokes');

        self::assertResponseRedirects('/login');
    }

    public function testIndexListsOnlyApprovedJokes(): void
    {
        $client = static::createClient();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $suffix = bin2hex(random_bytes(4));
        $approved = (new Joke())->setJoke("An approved API joke {$suffix}");
        $pending = (new Joke())->setJoke("A pending API joke {$suffix}")->setApproved(false);
        $entityManager->persist($approved);
        $entityManager->persist($pending);
        $entityManager->flush();

        $client->loginUser($this->createUser());
        $client->request('GET', '/api/jokes?limit=50');

        self::assertResponseIsSuccessful();
        self::assertJson($client->getResponse()->getContent());

        $data = json_decode($client->getResponse()->getContent(), true);
        $texts = array_column($data, 'joke');

        self::assertContains("An approved API joke {$suffix}", $texts);
        self::assertNotContains("A pending API joke {$suffix}", $texts);
    }

    public function testIndexFiltersByCategory(): void
    {
        $client = static::createClient();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $suffix = bin2hex(random_bytes(4));
        $devJoke = (new Joke())->setJoke("A dev API joke {$suffix}")->setCategories(['dev']);
        $foodJoke = (new Joke())->setJoke("A food API joke {$suffix}")->setCategories(['food']);
        $entityManager->persist($devJoke);
        $entityManager->persist($foodJoke);
        $entityManager->flush();

        $client->loginUser($this->createUser());
        $client->request('GET', '/api/jokes?category=dev&limit=50');

        $data = json_decode($client->getResponse()->getContent(), true);
        $texts = array_column($data, 'joke');

        self::assertContains("A dev API joke {$suffix}", $texts);
        self::assertNotContains("A food API joke {$suffix}", $texts);
    }

    public function testShowReturnsAJokeWithItsLikeCount(): void
    {
        $client = static::createClient();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $joke = (new Joke())->setJoke('An individually fetched API joke');
        $entityManager->persist($joke);
        $entityManager->flush();

        $liker = $this->createUser();
        $entityManager->persist(new JokeLike($liker, $joke));
        $entityManager->flush();

        $client->loginUser($this->createUser());
        $client->request('GET', '/api/jokes/'.$joke->getId());

        self::assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertSame($joke->getId(), $data['id']);
        self::assertSame('An individually fetched API joke', $data['joke']);
        self::assertSame(1, $data['likeCount']);
    }

    public function testShowReturns404ForPendingJoke(): void
    {
        $client = static::createClient();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $joke = (new Joke())->setJoke('A still-pending API joke')->setApproved(false);
        $entityManager->persist($joke);
        $entityManager->flush();

        $client->loginUser($this->createUser());
        $client->request('GET', '/api/jokes/'.$joke->getId());

        self::assertResponseStatusCodeSame(404);
    }

    public function testTopReturnsJokesRankedByLikeCount(): void
    {
        $client = static::createClient();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $suffix = bin2hex(random_bytes(4));
        $popular = (new Joke())->setJoke("A popular API joke {$suffix}");
        $entityManager->persist($popular);
        $entityManager->flush();

        $liker = $this->createUser();
        $entityManager->persist(new JokeLike($liker, $popular));
        $entityManager->flush();

        $client->loginUser($this->createUser());
        $client->request('GET', '/api/jokes/top?limit=50');

        self::assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);
        $entry = current(array_filter($data, static fn (array $row) => $row['joke'] === "A popular API joke {$suffix}"));

        self::assertNotFalse($entry);
        self::assertSame(1, $entry['likeCount']);
    }

    public function testRandomFiltersByCategoryWhenGiven(): void
    {
        $client = static::createClient();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $suffix = bin2hex(random_bytes(4));
        $devJoke = (new Joke())->setJoke("A random dev API joke {$suffix}")->setCategories(['dev']);
        $entityManager->persist($devJoke);
        $entityManager->flush();

        $client->loginUser($this->createUser());
        $client->request('GET', '/api/jokes/random?category=dev');

        self::assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertContains('dev', $data['categories']);
    }

    public function testRandomReturns404WhenNoJokeMatchesTheCategory(): void
    {
        $client = static::createClient();

        $client->loginUser($this->createUser());
        $client->request('GET', '/api/jokes/random?category='.bin2hex(random_bytes(8)));

        self::assertResponseStatusCodeSame(404);
    }

    public function testOfTheDayReturnsTheSelectedJokeWithADate(): void
    {
        $client = static::createClient();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $joke = (new Joke())->setJoke('The only candidate for joke of the day '.bin2hex(random_bytes(4)));
        $entityManager->persist($joke);
        $entityManager->flush();

        $client->loginUser($this->createUser());
        $client->request('GET', '/api/jokes/of-the-day');

        self::assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertSame((new \DateTimeImmutable('today'))->format('Y-m-d'), $data['date']);
        self::assertArrayHasKey('joke', $data);
        self::assertArrayHasKey('id', $data);
    }

    public function testSearchRequiresANonEmptyQuery(): void
    {
        $client = static::createClient();

        $client->loginUser($this->createUser());
        $client->request('GET', '/api/jokes/search');

        self::assertResponseStatusCodeSame(400);
    }

    public function testSearchFallsBackToTextSearchAndReturnsResults(): void
    {
        // No embeddings indexed and AI_PROVIDER=null in the test env - this exercises the same
        // full-text fallback SemanticJokeSearch already falls back to elsewhere.
        $client = static::createClient();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $uniqueWord = 'apisearchable'.bin2hex(random_bytes(4));
        $joke = (new Joke())->setJoke("Chuck Norris debugs {$uniqueWord} via the API.");
        $entityManager->persist($joke);
        $entityManager->flush();
        $jokeId = $joke->getId();

        StaticDriver::commit();
        StaticDriver::beginTransaction();

        try {
            $client->loginUser($this->createUser());
            $client->request('GET', '/api/jokes/search?q='.$uniqueWord);

            self::assertResponseIsSuccessful();
            $data = json_decode($client->getResponse()->getContent(), true);
            self::assertFalse($data['semantic']);
            self::assertContains($uniqueWord, array_map(
                static fn (array $result) => str_contains($result['joke'], $uniqueWord) ? $uniqueWord : null,
                $data['results'],
            ));
        } finally {
            $freshEntityManager = static::getContainer()->get(EntityManagerInterface::class);
            $freshEntityManager->createQuery('DELETE FROM App\Entity\Joke j WHERE j.id = :id')
                ->setParameter('id', $jokeId)
                ->execute();
            StaticDriver::commit();
            StaticDriver::beginTransaction();
        }
    }

    public function testSearchIsRateLimited(): void
    {
        $client = static::createClient();

        $client->loginUser($this->createUser());

        // The limiter allows 20 requests per minute; exhaust it, then the next one must be rejected.
        for ($i = 0; $i < 20; ++$i) {
            $client->request('GET', '/api/jokes/search?q=anything'.$i);
        }

        $client->request('GET', '/api/jokes/search?q=oneTooMany');
        self::assertResponseStatusCodeSame(429);
    }

    private function createUser(): User
    {
        $container = static::getContainer();

        $user = new User();
        $user->setEmail(sprintf('api-%s@example.com', bin2hex(random_bytes(4))));
        $user->setPassword($container->get(UserPasswordHasherInterface::class)->hashPassword($user, 'irrelevant'));

        $entityManager = $container->get(EntityManagerInterface::class);
        $entityManager->persist($user);
        $entityManager->flush();

        return $user;
    }
}
