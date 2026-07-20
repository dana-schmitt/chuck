<?php

namespace App\Tests\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class SecurityControllerTest extends WebTestCase
{
    public function testUserCanLogInWithValidCredentials(): void
    {
        $client = static::createClient();
        $this->resetLoginThrottling();
        $email = $this->createUser('correct-password');

        $crawler = $client->request('GET', '/login');
        $form = $crawler->selectButton('Chuck in')->form([
            'email' => $email,
            'password' => 'correct-password',
        ]);
        $client->submit($form);

        self::assertResponseRedirects('/joke');
    }

    public function testLoginFailsWithInvalidCredentials(): void
    {
        $client = static::createClient();
        $this->resetLoginThrottling();
        $email = $this->createUser('correct-password');

        $crawler = $client->request('GET', '/login');
        $form = $crawler->selectButton('Chuck in')->form([
            'email' => $email,
            'password' => 'wrong-password',
        ]);
        $client->submit($form);

        self::assertResponseRedirects('/login');
        $client->followRedirect();
        self::assertSelectorTextContains('body', 'Invalid credentials');
    }

    public function testLoginIsThrottledAfterTooManyFailedAttempts(): void
    {
        $client = static::createClient();
        $this->resetLoginThrottling();
        $email = $this->createUser('correct-password');

        for ($i = 0; $i < 5; ++$i) {
            $crawler = $client->request('GET', '/login');
            $form = $crawler->selectButton('Chuck in')->form([
                'email' => $email,
                'password' => 'wrong-password',
            ]);
            $client->submit($form);
        }

        // The 6th attempt must be throttled even though the password is now correct.
        $crawler = $client->request('GET', '/login');
        $form = $crawler->selectButton('Chuck in')->form([
            'email' => $email,
            'password' => 'correct-password',
        ]);
        $client->submit($form);

        self::assertResponseRedirects('/login');
        $client->followRedirect();
        self::assertSelectorTextContains('body', 'Too many failed login attempts');
    }

    private function createUser(string $plainPassword): string
    {
        $container = static::getContainer();
        $email = sprintf('login-%s@example.com', bin2hex(random_bytes(4)));

        $user = new User();
        $user->setEmail($email);
        $user->setPassword($container->get(UserPasswordHasherInterface::class)->hashPassword($user, $plainPassword));

        $entityManager = $container->get(EntityManagerInterface::class);
        $entityManager->persist($user);
        $entityManager->flush();

        return $email;
    }

    /**
     * login_throttling combines a per-username limiter with an IP-only global limiter. The global
     * one is keyed by IP alone, so previous tests' failed attempts (from this class or others) would
     * otherwise carry over and make login attempts spuriously fail here.
     */
    private function resetLoginThrottling(): void
    {
        static::getContainer()->get('cache.rate_limiter')->clear();
    }
}
