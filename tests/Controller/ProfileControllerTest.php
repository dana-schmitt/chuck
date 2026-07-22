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

    public function testUserCanUpdateDisplayName(): void
    {
        $client = static::createClient();
        $user = $this->createUser();
        $client->loginUser($user);

        $crawler = $client->request('GET', '/profile');
        $form = $crawler->selectButton('Save')->form([
            'profile_form[displayName]' => 'The Roundhouse King',
        ]);
        $client->submit($form);

        self::assertResponseRedirects('/profile');

        $refreshed = static::getContainer()->get(EntityManagerInterface::class)
            ->getRepository(User::class)
            ->find($user->getId());

        self::assertSame('The Roundhouse King', $refreshed->getDisplayName());
    }

    public function testUserCanUploadAnAvatarImage(): void
    {
        $client = static::createClient();
        $user = $this->createUser();
        $client->loginUser($user);

        $crawler = $client->request('GET', '/profile');
        $form = $crawler->selectButton('Save')->form();

        $tmpFile = tempnam(sys_get_temp_dir(), 'avatar').'.png';
        // A minimal 1x1 transparent PNG, just enough to pass the image/png mime check.
        file_put_contents($tmpFile, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='));

        $form['profile_form[avatarFile]']->upload($tmpFile);
        $client->submit($form);

        self::assertResponseRedirects('/profile');

        $refreshed = static::getContainer()->get(EntityManagerInterface::class)
            ->getRepository(User::class)
            ->find($user->getId());

        self::assertStringStartsWith('/uploads/avatars/', $refreshed->getAvatarUrl());

        $uploadedPath = static::getContainer()->getParameter('kernel.project_dir').'/public'.$refreshed->getAvatarUrl();
        self::assertFileExists($uploadedPath);

        unlink($uploadedPath);
        @unlink($tmpFile);
    }

    public function testAvatarUploadRejectsNonImageFiles(): void
    {
        $client = static::createClient();
        $user = $this->createUser();
        $client->loginUser($user);

        $crawler = $client->request('GET', '/profile');
        $form = $crawler->selectButton('Save')->form();

        $tmpFile = tempnam(sys_get_temp_dir(), 'notanimage').'.txt';
        file_put_contents($tmpFile, 'not an image');

        $form['profile_form[avatarFile]']->upload($tmpFile);
        $client->submit($form);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body', 'Please upload a JPEG, PNG, WebP or GIF image.');

        @unlink($tmpFile);
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
