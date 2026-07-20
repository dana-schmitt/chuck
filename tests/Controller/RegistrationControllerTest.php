<?php

namespace App\Tests\Controller;

use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class RegistrationControllerTest extends WebTestCase
{
    public function testUserCanRegisterAndIsLoggedInAfterwards(): void
    {
        $client = static::createClient();
        // Registration logs the user in via the same passport/login_throttling pipeline as a normal
        // login. Its IP-based global limiter is shared with SecurityControllerTest, so start clean.
        static::getContainer()->get('cache.rate_limiter')->clear();
        $email = sprintf('register-%s@example.com', bin2hex(random_bytes(4)));

        $crawler = $client->request('GET', '/register');

        $form = $crawler->selectButton('CHUCK ME')->form([
            'registration_form[email]' => $email,
            'registration_form[plainPassword]' => 'a-secure-password',
        ]);

        $client->submit($form);

        self::assertResponseRedirects('/joke');

        $user = static::getContainer()->get(UserRepository::class)->findOneBy(['email' => $email]);
        self::assertNotNull($user);

        // The user was authenticated as part of registration, so following the
        // redirect must land on /joke rather than bouncing back to /login.
        $client->followRedirect();
        self::assertResponseIsSuccessful();
    }

    public function testRegistrationFailsWithInvalidPassword(): void
    {
        $client = static::createClient();
        $email = sprintf('invalid-%s@example.com', bin2hex(random_bytes(4)));

        $crawler = $client->request('GET', '/register');

        $form = $crawler->selectButton('CHUCK ME')->form([
            'registration_form[email]' => $email,
            'registration_form[plainPassword]' => 'a',
        ]);

        $client->submit($form);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body', 'Your password should be at least');

        $user = static::getContainer()->get(UserRepository::class)->findOneBy(['email' => $email]);
        self::assertNull($user);
    }
}
