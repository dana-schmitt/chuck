<?php

namespace App\Tests\Controller;

use App\Entity\Joke;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AdminControllerTest extends WebTestCase
{
    public function testAdminDashboardRedirectsAnonymousUsersToLogin(): void
    {
        $client = static::createClient();
        $client->request('GET', '/admin');

        self::assertResponseRedirects('/login');
    }

    public function testAdminDashboardIsForbiddenForRegularUsers(): void
    {
        $client = static::createClient();
        $client->loginUser($this->createUser());

        $client->request('GET', '/admin');

        self::assertResponseStatusCodeSame(403);
    }

    public function testAdminCanViewDashboardAndUserList(): void
    {
        $client = static::createClient();
        $client->loginUser($this->createUser(admin: true));

        $client->request('GET', '/admin');
        self::assertResponseIsSuccessful();

        $client->request('GET', '/admin/users');
        self::assertResponseIsSuccessful();
    }

    public function testAdminCanToggleAnotherUsersAdminRole(): void
    {
        $client = static::createClient();
        $admin = $this->createUser(admin: true);
        $client->loginUser($admin);

        $target = $this->createUser();
        self::assertFalse(\in_array('ROLE_ADMIN', $target->getRoles(), true));

        $crawler = $client->request('GET', '/admin/users');
        $form = $crawler->filter(sprintf('form[action="/admin/users/%d/toggle-admin"]', $target->getId()))->form();
        $client->submit($form);

        self::assertResponseRedirects('/admin/users');

        $refreshed = static::getContainer()->get(EntityManagerInterface::class)
            ->getRepository(User::class)
            ->find($target->getId());
        self::assertTrue(\in_array('ROLE_ADMIN', $refreshed->getRoles(), true));
    }

    public function testAdminCannotToggleTheirOwnAdminRole(): void
    {
        $client = static::createClient();
        $admin = $this->createUser(admin: true);
        $client->loginUser($admin);

        $crawler = $client->request('GET', '/admin/users');
        $form = $crawler->filter(sprintf('form[action="/admin/users/%d/toggle-admin"]', $admin->getId()))->form();
        $client->submit($form);

        self::assertResponseRedirects('/admin/users');
        $client->followRedirect();
        self::assertSelectorTextContains('body', 'cannot change your own admin status');
    }

    public function testAdminCanDeleteAJoke(): void
    {
        $client = static::createClient();
        $client->loginUser($this->createUser(admin: true));

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $joke = (new Joke())->setJoke('A joke destined for deletion');
        $entityManager->persist($joke);
        $entityManager->flush();
        $jokeId = $joke->getId();

        $crawler = $client->request('GET', '/admin/jokes');
        $form = $crawler->filter(sprintf('form[action="/admin/jokes/%d/delete"]', $jokeId))->form();
        $client->submit($form);

        self::assertResponseRedirects('/admin/jokes');
        self::assertNull($entityManager->getRepository(Joke::class)->find($jokeId));
    }

    private function createUser(bool $admin = false): User
    {
        $container = static::getContainer();

        $user = new User();
        $user->setEmail(sprintf('%s-%s@example.com', $admin ? 'admin' : 'user', bin2hex(random_bytes(4))));
        $user->setPassword($container->get(UserPasswordHasherInterface::class)->hashPassword($user, 'irrelevant'));
        if ($admin) {
            $user->setRoles(['ROLE_ADMIN']);
        }

        $entityManager = $container->get(EntityManagerInterface::class);
        $entityManager->persist($user);
        $entityManager->flush();

        return $user;
    }
}
