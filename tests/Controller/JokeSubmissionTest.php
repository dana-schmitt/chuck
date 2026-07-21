<?php

namespace App\Tests\Controller;

use App\Entity\Joke;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class JokeSubmissionTest extends WebTestCase
{
    public function testSubmitPageRedirectsAnonymousUsersToLogin(): void
    {
        $client = static::createClient();
        $client->request('GET', '/joke/submit');

        self::assertResponseRedirects('/login');
    }

    public function testUserCanSubmitAJokeAndItStartsUnapproved(): void
    {
        $client = static::createClient();
        $client->loginUser($this->createUser());

        $crawler = $client->request('GET', '/joke/submit');
        $form = $crawler->selectButton('Submit joke')->form([
            'joke_submission_form[joke]' => 'Chuck Norris can divide by zero and live to tell about it.',
            'joke_submission_form[category]' => 'dev',
        ]);
        $client->submit($form);

        self::assertResponseRedirects('/joke/submit');

        $joke = static::getContainer()->get(EntityManagerInterface::class)
            ->getRepository(Joke::class)
            ->findOneBy(['joke' => 'Chuck Norris can divide by zero and live to tell about it.']);

        self::assertNotNull($joke);
        self::assertFalse($joke->isApproved());
        self::assertSame(['dev'], $joke->getCategories());
        self::assertNotNull($joke->getSubmittedBy());
    }

    public function testSubmissionFailsForTooShortJoke(): void
    {
        $client = static::createClient();
        $client->loginUser($this->createUser());

        $crawler = $client->request('GET', '/joke/submit');
        $form = $crawler->selectButton('Submit joke')->form([
            'joke_submission_form[joke]' => 'Too short',
        ]);
        $client->submit($form);

        self::assertResponseStatusCodeSame(422);

        $joke = static::getContainer()->get(EntityManagerInterface::class)
            ->getRepository(Joke::class)
            ->findOneBy(['joke' => 'Too short']);
        self::assertNull($joke);
    }

    public function testUnapprovedJokeIsHiddenFromOtherUsersButVisibleToSubmitterAndAdmin(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);
        $passwordHasher = $container->get(UserPasswordHasherInterface::class);

        $submitter = $this->createUser($entityManager, $passwordHasher);
        $joke = (new Joke())->setJoke('A pending joke only the submitter should see')->setApproved(false)->setSubmittedBy($submitter);
        $entityManager->persist($joke);
        $entityManager->flush();

        // Another regular user gets a 404.
        $client->loginUser($this->createUser($entityManager, $passwordHasher));
        $client->request('GET', '/joke/'.$joke->getId());
        self::assertResponseStatusCodeSame(404);

        // The submitter can see it.
        $client->loginUser($submitter);
        $client->request('GET', '/joke/'.$joke->getId());
        self::assertResponseIsSuccessful();

        // An admin can see it too.
        $admin = $this->createUser($entityManager, $passwordHasher, admin: true);
        $client->loginUser($admin);
        $client->request('GET', '/joke/'.$joke->getId());
        self::assertResponseIsSuccessful();
    }

    public function testUnapprovedJokesAreExcludedFromTheTopJokesLeaderboard(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);
        $passwordHasher = $container->get(UserPasswordHasherInterface::class);

        $suffix = bin2hex(random_bytes(4));
        $pendingJoke = (new Joke())->setJoke("A pending but liked joke {$suffix}")->setApproved(false);
        $entityManager->persist($pendingJoke);
        $entityManager->flush();

        $liker = $this->createUser($entityManager, $passwordHasher);
        $entityManager->persist(new \App\Entity\JokeLike($liker, $pendingJoke));
        $entityManager->flush();

        $client->loginUser($this->createUser($entityManager, $passwordHasher));
        $crawler = $client->request('GET', '/top');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextNotContains('body', "A pending but liked joke {$suffix}");
    }

    public function testAdminCanApproveAPendingJoke(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);
        $passwordHasher = $container->get(UserPasswordHasherInterface::class);

        $joke = (new Joke())->setJoke('A joke awaiting admin approval')->setApproved(false);
        $entityManager->persist($joke);
        $entityManager->flush();
        $jokeId = $joke->getId();

        $client->loginUser($this->createUser($entityManager, $passwordHasher, admin: true));

        $crawler = $client->request('GET', '/admin/jokes');
        self::assertSelectorTextContains('body', 'Pending');

        $form = $crawler->filter(sprintf('form[action="/admin/jokes/%d/approve"]', $jokeId))->form();
        $client->submit($form);

        self::assertResponseRedirects('/admin/jokes');

        $refreshed = $entityManager->getRepository(Joke::class)->find($jokeId);
        self::assertTrue($refreshed->isApproved());
    }

    private function createUser(?EntityManagerInterface $entityManager = null, ?UserPasswordHasherInterface $passwordHasher = null, bool $admin = false): User
    {
        $container = static::getContainer();
        $entityManager ??= $container->get(EntityManagerInterface::class);
        $passwordHasher ??= $container->get(UserPasswordHasherInterface::class);

        $user = new User();
        $user->setEmail(sprintf('%s-%s@example.com', $admin ? 'admin' : 'user', bin2hex(random_bytes(4))));
        $user->setPassword($passwordHasher->hashPassword($user, 'irrelevant'));
        if ($admin) {
            $user->setRoles(['ROLE_ADMIN']);
        }

        $entityManager->persist($user);
        $entityManager->flush();

        return $user;
    }
}
