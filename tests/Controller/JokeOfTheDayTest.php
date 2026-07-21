<?php

namespace App\Tests\Controller;

use App\Entity\Joke;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class JokeOfTheDayTest extends WebTestCase
{
    public function testJokeOfTheDayPageRedirectsAnonymousUsersToLogin(): void
    {
        $client = static::createClient();
        $client->request('GET', '/joke-of-the-day');

        self::assertResponseRedirects('/login');
    }

    public function testJokeOfTheDayPageDisplaysAJokeWithLikeAndShareButtons(): void
    {
        $client = static::createClient();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $joke = (new Joke())->setJoke('A featured Chuck fact for the day '.bin2hex(random_bytes(4)));
        $entityManager->persist($joke);
        $entityManager->flush();

        $client->loginUser($this->createUser($entityManager));
        $crawler = $client->request('GET', '/joke-of-the-day');

        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('[data-like-url-value]'));
        self::assertCount(1, $crawler->filter('[data-share-url-value]'));
    }

    public function testJokeOfTheDayIsStableAcrossRequestsOnTheSameDay(): void
    {
        $client = static::createClient();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $joke = (new Joke())->setJoke('A stable Chuck fact for the day '.bin2hex(random_bytes(4)));
        $entityManager->persist($joke);
        $entityManager->flush();

        $client->loginUser($this->createUser($entityManager));

        $first = $client->request('GET', '/joke-of-the-day');
        $firstText = $first->filter('#joke-container p')->text();

        $second = $client->request('GET', '/joke-of-the-day');
        $secondText = $second->filter('#joke-container p')->text();

        self::assertSame($firstText, $secondText);
    }

    private function createUser(EntityManagerInterface $entityManager): User
    {
        $user = new User();
        $user->setEmail(sprintf('jotd-viewer-%s@example.com', bin2hex(random_bytes(4))));
        $user->setPassword(
            static::getContainer()->get(UserPasswordHasherInterface::class)->hashPassword($user, 'irrelevant-password'),
        );

        $entityManager->persist($user);
        $entityManager->flush();

        return $user;
    }
}
