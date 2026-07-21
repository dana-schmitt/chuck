<?php

namespace App\Tests\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class VerifyEmailControllerTest extends WebTestCase
{
    public function testVerifyEmailPageRedirectsAnonymousUsersToLogin(): void
    {
        $client = static::createClient();
        $client->request('GET', '/verify/email');

        self::assertResponseRedirects('/login');
    }

    public function testUserCanVerifyEmailAfterRegistering(): void
    {
        $client = static::createClient();
        static::getContainer()->get('cache.rate_limiter')->clear();
        $email = sprintf('verify-%s@example.com', bin2hex(random_bytes(4)));

        $crawler = $client->request('GET', '/register');
        $form = $crawler->selectButton('CHUCK ME')->form([
            'registration_form[email]' => $email,
            'registration_form[plainPassword]' => 'a-secure-password',
        ]);
        $client->submit($form);

        self::assertEmailCount(1);
        $confirmationEmail = self::getMailerMessage();
        self::assertNotNull($confirmationEmail);

        $client->followRedirect();

        $path = $this->extractVerificationPath((string) $confirmationEmail->getHtmlBody());

        $client->request('GET', $path);
        self::assertResponseRedirects('/joke');

        $user = static::getContainer()->get(EntityManagerInterface::class)
            ->getRepository(User::class)
            ->findOneBy(['email' => $email]);
        self::assertNotNull($user);
        self::assertTrue($user->isVerified());
    }

    public function testResendSendsANewVerificationEmailForUnverifiedUsers(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);
        $passwordHasher = $container->get(UserPasswordHasherInterface::class);

        $user = new User();
        $user->setEmail(sprintf('resend-%s@example.com', bin2hex(random_bytes(4))));
        $user->setPassword($passwordHasher->hashPassword($user, 'irrelevant'));
        $entityManager->persist($user);
        $entityManager->flush();

        $client->loginUser($user);
        $client->request('GET', '/verify/email/resend');

        self::assertResponseRedirects('/joke');
        self::assertEmailCount(1);
    }

    public function testResendDoesNothingForAlreadyVerifiedUsers(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);
        $passwordHasher = $container->get(UserPasswordHasherInterface::class);

        $user = new User();
        $user->setEmail(sprintf('verified-%s@example.com', bin2hex(random_bytes(4))));
        $user->setPassword($passwordHasher->hashPassword($user, 'irrelevant'));
        $user->setIsVerified(true);
        $entityManager->persist($user);
        $entityManager->flush();

        $client->loginUser($user);
        $client->request('GET', '/verify/email/resend');

        self::assertResponseRedirects('/joke');
        self::assertEmailCount(0);
    }

    private function extractVerificationPath(string $htmlBody): string
    {
        preg_match('#https?://[^\s"<]+/verify/email\?[^\s"<]+#', $htmlBody, $matches);
        self::assertNotEmpty($matches, 'Could not find the verification link in the email body.');

        $url = html_entity_decode($matches[0]);

        return parse_url($url, PHP_URL_PATH).'?'.parse_url($url, PHP_URL_QUERY);
    }
}
