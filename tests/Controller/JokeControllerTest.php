<?php

namespace App\Tests\Controller;

use App\Entity\Joke;
use App\Entity\JokeLike;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class JokeControllerTest extends WebTestCase
{
    public function testHomepageIsAccessible(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Chuck Norris');
    }

    public function testJokePageRedirectsAnonymousUsersToLogin(): void
    {
        $client = static::createClient();
        $client->request('GET', '/joke');

        self::assertResponseRedirects('/login');
    }

    public function testJokePageShowsJokeForAuthenticatedUser(): void
    {
        $client = static::createClient();
        $container = static::getContainer();

        $container->set('chuck_norris_jokes.client', new MockHttpClient([
            new MockResponse(json_encode(['value' => 'Test joke from mock API'])),
        ]));

        $user = new User();
        $user->setEmail(sprintf('joke-viewer-%s@example.com', bin2hex(random_bytes(4))));
        $user->setPassword($container->get(UserPasswordHasherInterface::class)->hashPassword($user, 'irrelevant-password'));

        $entityManager = $container->get(EntityManagerInterface::class);
        $entityManager->persist($user);
        $entityManager->flush();

        $client->loginUser($user);
        $client->request('GET', '/joke');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Test joke from mock API');
    }

    public function testTopJokesPageRedirectsAnonymousUsersToLogin(): void
    {
        $client = static::createClient();
        $client->request('GET', '/top');

        self::assertResponseRedirects('/login');
    }

    public function testTopJokesPageRanksJokesByLikeCount(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);
        $passwordHasher = $container->get(UserPasswordHasherInterface::class);

        // Unique text per run: the test database isn't reset between test methods/runs,
        // so reusing a fixed joke text would accumulate duplicate rows and stale like
        // counts across runs instead of reflecting only what this test just created.
        $suffix = bin2hex(random_bytes(4));
        $popularJoke = (new Joke())->setJoke("The joke everyone loves {$suffix}");
        $unpopularJoke = (new Joke())->setJoke("The joke nobody likes {$suffix}");
        $entityManager->persist($popularJoke);
        $entityManager->persist($unpopularJoke);
        $entityManager->flush();

        $liker1 = $this->createUser($entityManager, $passwordHasher);
        $liker2 = $this->createUser($entityManager, $passwordHasher);
        $entityManager->persist(new JokeLike($liker1, $popularJoke));
        $entityManager->persist(new JokeLike($liker2, $popularJoke));
        $entityManager->persist(new JokeLike($liker1, $unpopularJoke));
        $entityManager->flush();

        $client->loginUser($this->createUser($entityManager, $passwordHasher));
        $crawler = $client->request('GET', '/top');

        self::assertResponseIsSuccessful();

        $entries = $crawler->filter('ol li');
        $texts = $entries->each(static fn ($node) => $node->text());

        $popularIndex = null;
        $unpopularIndex = null;
        foreach ($texts as $index => $text) {
            if (str_contains($text, "The joke everyone loves {$suffix}")) {
                $popularIndex = $index;
                self::assertStringContainsString('2', $text);
            }
            if (str_contains($text, "The joke nobody likes {$suffix}")) {
                $unpopularIndex = $index;
            }
        }

        self::assertNotNull($popularIndex, 'The 2-like joke should appear on the leaderboard.');
        self::assertNotNull($unpopularIndex, 'The 1-like joke should appear on the leaderboard.');
        self::assertLessThan($unpopularIndex, $popularIndex, 'The more-liked joke should rank above the less-liked one.');
    }

    private function createUser(EntityManagerInterface $entityManager, UserPasswordHasherInterface $passwordHasher): User
    {
        $user = new User();
        $user->setEmail(sprintf('user-%s@example.com', bin2hex(random_bytes(4))));
        $user->setPassword($passwordHasher->hashPassword($user, 'irrelevant-password'));

        $entityManager->persist($user);
        $entityManager->flush();

        return $user;
    }
}
