<?php

namespace App\Tests\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class LikeControllerTest extends WebTestCase
{
    public function testAnonymousUserCannotLike(): void
    {
        $client = static::createClient();
        $client->request('POST', '/joke/1/like');

        self::assertResponseRedirects('/login');
    }

    public function testUserCanLikeAndUnlikeAJoke(): void
    {
        $client = static::createClient();
        $this->mockJokeApi($client, 'A likeable Chuck fact');
        $user = $this->createAndLoginUser($client);

        // Render the joke page to obtain the persisted joke's like URL + CSRF token.
        $crawler = $client->request('GET', '/joke');
        self::assertResponseIsSuccessful();

        $button = $crawler->filter('[data-like-url-value]');
        self::assertCount(1, $button, 'The joke page should render a like button.');
        $url = $button->attr('data-like-url-value');
        $token = $button->attr('data-like-token-value');

        // First click -> liked.
        $client->request('POST', $url, [], [], ['HTTP_X_CSRF_TOKEN' => $token]);
        self::assertResponseIsSuccessful();
        self::assertSame(['liked' => true, 'likeCount' => 1], json_decode($client->getResponse()->getContent(), true));

        // The liked joke shows up on the liked page.
        $likedCrawler = $client->request('GET', '/liked');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'A likeable Chuck fact');

        // Second click on the same joke -> unliked.
        $client->request('POST', $url, [], [], ['HTTP_X_CSRF_TOKEN' => $token]);
        self::assertResponseIsSuccessful();
        self::assertSame(['liked' => false, 'likeCount' => 0], json_decode($client->getResponse()->getContent(), true));

        // Liked page is empty again.
        $client->request('GET', '/liked');
        self::assertSelectorTextContains('body', "haven't liked any jokes yet");

        self::assertNotNull($user->getId());
    }

    public function testLikeIsRejectedWithoutValidCsrfToken(): void
    {
        $client = static::createClient();
        $this->mockJokeApi($client, 'Another Chuck fact');
        $this->createAndLoginUser($client);

        $crawler = $client->request('GET', '/joke');
        $url = $crawler->filter('[data-like-url-value]')->attr('data-like-url-value');

        $client->request('POST', $url, [], [], ['HTTP_X_CSRF_TOKEN' => 'not-a-valid-token']);

        self::assertResponseStatusCodeSame(403);
    }

    private function mockJokeApi(object $client, string $joke): void
    {
        static::getContainer()->set('chuck_norris_jokes.client', new MockHttpClient([
            new MockResponse(json_encode(['value' => $joke])),
            new MockResponse(json_encode(['value' => $joke])),
        ]));
    }

    private function createAndLoginUser(object $client): User
    {
        $container = static::getContainer();

        $user = new User();
        $user->setEmail(sprintf('liker-%s@example.com', bin2hex(random_bytes(4))));
        $user->setPassword($container->get(UserPasswordHasherInterface::class)->hashPassword($user, 'irrelevant'));

        $entityManager = $container->get(EntityManagerInterface::class);
        $entityManager->persist($user);
        $entityManager->flush();

        $client->loginUser($user);

        return $user;
    }
}
