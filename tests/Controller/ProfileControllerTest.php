<?php

namespace App\Tests\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class ProfileControllerTest extends WebTestCase
{
    public function testProfilePageRedirectsAnonymousUsersToLogin(): void
    {
        $client = static::createClient();
        $client->request('GET', '/profile');

        self::assertResponseRedirects('/login');
    }

    public function testDisplayNameDefaultsToTheLocalPartOfTheEmail(): void
    {
        $client = static::createClient();
        $client->loginUser($this->createUser('chuck.norris@example.com'));

        $crawler = $client->request('GET', '/profile');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'chuck.norris');
    }

    public function testUserCanUpdateDisplayNameAndAvatarUrl(): void
    {
        $client = static::createClient();
        $user = $this->createUser();
        $client->loginUser($user);

        $crawler = $client->request('GET', '/profile');
        $form = $crawler->selectButton('Save')->form([
            'profile_form[displayName]' => 'The Roundhouse King',
            'profile_form[avatarUrl]' => 'https://example.com/avatar.png',
        ]);
        $client->submit($form);

        self::assertResponseRedirects('/profile');

        $refreshed = static::getContainer()->get(EntityManagerInterface::class)
            ->getRepository(User::class)
            ->find($user->getId());

        self::assertSame('The Roundhouse King', $refreshed->getDisplayName());
        self::assertSame('https://example.com/avatar.png', $refreshed->getAvatarUrl());
    }

    public function testAvatarFallsBackToGravatarWhenNotSet(): void
    {
        $user = $this->createUser('someone@example.com');

        self::assertStringStartsWith('https://www.gravatar.com/avatar/', $user->getAvatarUrl());
    }

    private function createUser(?string $email = null): User
    {
        $container = static::getContainer();

        $user = new User();
        $user->setEmail($email ?? sprintf('profile-%s@example.com', bin2hex(random_bytes(4))));
        $user->setPassword($container->get(UserPasswordHasherInterface::class)->hashPassword($user, 'irrelevant'));

        $entityManager = $container->get(EntityManagerInterface::class);
        $entityManager->persist($user);
        $entityManager->flush();

        return $user;
    }
}
