<?php

namespace App\Tests\Controller;

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
}
