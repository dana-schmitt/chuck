<?php

namespace App\Tests\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class ResetPasswordControllerTest extends WebTestCase
{
    public function testRequestingResetForUnknownEmailDoesNotRevealAccountExistence(): void
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/reset-password');
        $form = $crawler->selectButton('Send reset link')->form([
            'reset_password_request_form[email]' => 'nobody@example.com',
        ]);
        $client->submit($form);

        self::assertResponseRedirects('/reset-password/check-email');
        self::assertEmailCount(0);
    }

    public function testUserCanResetPassword(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);
        $passwordHasher = $container->get(UserPasswordHasherInterface::class);

        $email = sprintf('reset-%s@example.com', bin2hex(random_bytes(4)));
        $user = new User();
        $user->setEmail($email);
        $user->setPassword($passwordHasher->hashPassword($user, 'old-password'));
        $entityManager->persist($user);
        $entityManager->flush();

        $crawler = $client->request('GET', '/reset-password');
        $form = $crawler->selectButton('Send reset link')->form([
            'reset_password_request_form[email]' => $email,
        ]);
        $client->submit($form);

        self::assertResponseRedirects('/reset-password/check-email');
        self::assertEmailCount(1);

        $resetEmail = self::getMailerMessage();
        self::assertNotNull($resetEmail);
        preg_match('#/reset-password/reset/([\w-]+)#', (string) $resetEmail->getHtmlBody(), $matches);
        self::assertNotEmpty($matches, 'Could not find the reset link in the email body.');
        $token = $matches[1];

        // Visiting the tokenised URL stores the token in session and redirects to the plain form URL.
        $client->request('GET', '/reset-password/reset/'.$token);
        self::assertResponseRedirects('/reset-password/reset');

        $crawler = $client->request('GET', '/reset-password/reset');
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('Reset password')->form([
            'change_password_form[plainPassword][first]' => 'a-brand-new-password',
            'change_password_form[plainPassword][second]' => 'a-brand-new-password',
        ]);
        $client->submit($form);

        self::assertResponseRedirects('/joke');

        // The kernel (and with it the entity manager) has been rebooted since $user was
        // fetched, so re-fetch it from the current container's manager instead of refreshing
        // the now-detached original instance.
        $freshEntityManager = static::getContainer()->get(EntityManagerInterface::class);
        $freshUser = $freshEntityManager->getRepository(User::class)->findOneBy(['email' => $email]);
        self::assertNotNull($freshUser);
        self::assertTrue($passwordHasher->isPasswordValid($freshUser, 'a-brand-new-password'));
        self::assertFalse($passwordHasher->isPasswordValid($freshUser, 'old-password'));
    }
}
