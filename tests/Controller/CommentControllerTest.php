<?php

namespace App\Tests\Controller;

use App\Entity\Joke;
use App\Entity\JokeComment;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class CommentControllerTest extends WebTestCase
{
    public function testAnonymousUserCannotComment(): void
    {
        $client = static::createClient();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $joke = $this->createJoke($entityManager);

        $client->request('POST', "/joke/{$joke->getId()}/comments", ['comment_form' => ['body' => 'Nice one!']]);

        self::assertResponseRedirects('/login');
    }

    public function testUserCanPostAComment(): void
    {
        $client = static::createClient();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $joke = $this->createJoke($entityManager);

        $client->loginUser($this->createUser($entityManager));
        $crawler = $client->request('GET', '/joke/'.$joke->getId());

        $form = $crawler->filter('form[action*="/comments"]')->form([
            'comment_form[body]' => 'This one actually made me laugh.',
        ]);
        $client->submit($form);

        self::assertResponseRedirects('/joke/'.$joke->getId());
        $client->followRedirect();

        self::assertSelectorTextContains('body', 'This one actually made me laugh.');
    }

    public function testCommentIsRejectedWhenEmpty(): void
    {
        $client = static::createClient();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $joke = $this->createJoke($entityManager);

        $client->loginUser($this->createUser($entityManager));
        $crawler = $client->request('GET', '/joke/'.$joke->getId());

        $form = $crawler->filter('form[action*="/comments"]')->form([
            'comment_form[body]' => '',
        ]);
        $client->submit($form);

        self::assertResponseRedirects('/joke/'.$joke->getId());
        $client->followRedirect();

        self::assertSelectorTextContains('body', 'Please write a comment');
    }

    public function testCommentAuthorCanDeleteTheirOwnComment(): void
    {
        $client = static::createClient();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $joke = $this->createJoke($entityManager);
        $author = $this->createUser($entityManager);

        $comment = new JokeComment($joke, $author, 'A comment to be deleted');
        $entityManager->persist($comment);
        $entityManager->flush();

        $client->loginUser($author);
        $crawler = $client->request('GET', '/joke/'.$joke->getId());

        self::assertSelectorTextContains('body', 'A comment to be deleted');

        $form = $crawler->filter('form[action*="/delete"]')->form();
        $client->submit($form);

        self::assertResponseRedirects('/joke/'.$joke->getId());
        $crawlerAfter = $client->followRedirect();

        self::assertStringNotContainsString('A comment to be deleted', $crawlerAfter->filter('body')->text());
    }

    public function testOtherUserCannotDeleteSomeoneElsesComment(): void
    {
        $client = static::createClient();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $joke = $this->createJoke($entityManager);
        $author = $this->createUser($entityManager);

        $comment = new JokeComment($joke, $author, 'Only the author may remove this');
        $entityManager->persist($comment);
        $entityManager->flush();

        $otherUser = $this->createUser($entityManager);
        $client->loginUser($otherUser);
        $crawler = $client->request('GET', '/joke/'.$joke->getId());

        // Not the author and not an admin - no delete form should even render.
        self::assertCount(0, $crawler->filter('form[action*="/delete"]'));

        // Directly attempt the action anyway to confirm server-side enforcement.
        $client->request('POST', "/joke/{$joke->getId()}/comments/{$comment->getId()}/delete", ['_token' => 'irrelevant']);

        self::assertResponseStatusCodeSame(403);
    }

    public function testAdminCanDeleteAnyonesComment(): void
    {
        $client = static::createClient();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $joke = $this->createJoke($entityManager);
        $author = $this->createUser($entityManager);

        $comment = new JokeComment($joke, $author, 'Removable by an admin');
        $entityManager->persist($comment);
        $entityManager->flush();

        $client->loginUser($this->createUser($entityManager, admin: true));
        $crawler = $client->request('GET', '/joke/'.$joke->getId());

        $form = $crawler->filter('form[action*="/delete"]')->form();
        $client->submit($form);

        self::assertResponseRedirects('/joke/'.$joke->getId());
        $crawlerAfter = $client->followRedirect();

        self::assertStringNotContainsString('Removable by an admin', $crawlerAfter->filter('body')->text());
    }

    public function testUserCanToggleAReactionOnAComment(): void
    {
        $client = static::createClient();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $joke = $this->createJoke($entityManager);
        $author = $this->createUser($entityManager);

        $comment = new JokeComment($joke, $author, 'React to me');
        $entityManager->persist($comment);
        $entityManager->flush();

        $client->loginUser($this->createUser($entityManager));
        $crawler = $client->request('GET', '/joke/'.$joke->getId());

        $container = $crawler->filter('[data-controller="reaction"]');
        $url = $container->attr('data-reaction-url-value');
        $token = $container->attr('data-reaction-token-value');
        $emoji = $container->filter('[data-reaction-target="button"]')->first()->attr('data-emoji');

        self::assertNotNull($url);
        self::assertNotNull($emoji);

        $client->request('POST', $url, ['emoji' => $emoji], [], ['HTTP_X_CSRF_TOKEN' => $token]);
        self::assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertTrue($data['reacted']);
        self::assertSame(1, $data['counts'][$emoji]);

        // Toggling again removes the reaction.
        $client->request('POST', $url, ['emoji' => $emoji], [], ['HTTP_X_CSRF_TOKEN' => $token]);
        self::assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertFalse($data['reacted']);
        self::assertArrayNotHasKey($emoji, $data['counts']);
    }

    public function testReactionIsRejectedWithoutValidCsrfToken(): void
    {
        $client = static::createClient();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $joke = $this->createJoke($entityManager);
        $author = $this->createUser($entityManager);

        $comment = new JokeComment($joke, $author, 'React to me too');
        $entityManager->persist($comment);
        $entityManager->flush();

        $client->loginUser($this->createUser($entityManager));

        $client->request('POST', "/joke/{$joke->getId()}/comments/{$comment->getId()}/react", ['emoji' => '👍'], [], ['HTTP_X_CSRF_TOKEN' => 'not-a-valid-token']);

        self::assertResponseStatusCodeSame(403);
    }

    private function createJoke(EntityManagerInterface $entityManager): Joke
    {
        $joke = (new Joke())->setJoke('A commentable Chuck fact '.bin2hex(random_bytes(4)));
        $entityManager->persist($joke);
        $entityManager->flush();

        return $joke;
    }

    private function createUser(EntityManagerInterface $entityManager, bool $admin = false): User
    {
        $user = new User();
        $user->setEmail(sprintf('%s-%s@example.com', $admin ? 'admin' : 'commenter', bin2hex(random_bytes(4))));
        $user->setPassword(
            static::getContainer()->get(UserPasswordHasherInterface::class)->hashPassword($user, 'irrelevant-password'),
        );
        if ($admin) {
            $user->setRoles(['ROLE_ADMIN']);
        }

        $entityManager->persist($user);
        $entityManager->flush();

        return $user;
    }
}
