<?php

namespace App\Tests\Controller;

use App\Ai\CompletionProviderInterface;
use App\Entity\Joke;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class JokeExplainTest extends WebTestCase
{
    public function testAnonymousUserCannotRequestAnExplanation(): void
    {
        $client = static::createClient();
        $joke = $this->createJoke();

        $client->request('POST', '/joke/'.$joke->getId().'/explain');

        self::assertResponseRedirects('/login');
    }

    public function testExplainButtonAppearsOnTheJokeDetailPage(): void
    {
        $client = static::createClient();
        $joke = $this->createJoke();
        $client->loginUser($this->createUser());

        $crawler = $client->request('GET', '/joke/'.$joke->getId());

        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('[data-explain-url-value]'));
    }

    public function testExplainButtonAlsoAppearsOnTheRandomJokePage(): void
    {
        $client = static::createClient();
        static::getContainer()->set('chuck_norris_jokes.client', new MockHttpClient(
            new MockResponse(json_encode(['value' => 'A randomly fetched explainable joke']))
        ));
        $client->loginUser($this->createUser());

        $crawler = $client->request('GET', '/joke');

        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('[data-explain-url-value]'));
    }

    public function testUserCanRequestAnExplanationAndItIsCachedOnASecondRequest(): void
    {
        $client = static::createClient();
        // The kernel reboots (and the container - and any override on it - is discarded) before
        // every request after the first one performed on a client. Disable that here so the
        // mocked provider below stays live for the second (explain) request too, after the first
        // (page render, to fetch a real CSRF token) request already used it up.
        $client->disableReboot();
        $joke = $this->createJoke();
        $client->loginUser($this->createUser());

        $completionProvider = $this->createMock(CompletionProviderInterface::class);
        $completionProvider->expects($this->once())->method('complete')->willReturn('This plays on Chuck Norris folklore.');
        static::getContainer()->set(CompletionProviderInterface::class, $completionProvider);

        $crawler = $client->request('GET', '/joke/'.$joke->getId());
        $element = $crawler->filter('[data-explain-url-value]');
        $url = $element->attr('data-explain-url-value');
        $token = $element->attr('data-explain-token-value');

        $client->request('POST', $url, ['locale' => 'en'], [], ['HTTP_X_CSRF_TOKEN' => $token]);
        self::assertResponseIsSuccessful();
        self::assertSame(
            ['explanation' => 'This plays on Chuck Norris folklore.'],
            json_decode($client->getResponse()->getContent(), true),
        );

        // Second request for the same joke+locale must hit the cache, not the LLM again -
        // the mock's expects($this->once()) above enforces this.
        $client->request('POST', $url, ['locale' => 'en'], [], ['HTTP_X_CSRF_TOKEN' => $token]);
        self::assertResponseIsSuccessful();
        self::assertSame(
            ['explanation' => 'This plays on Chuck Norris folklore.'],
            json_decode($client->getResponse()->getContent(), true),
        );
    }

    public function testExplainIsRejectedWithoutAValidCsrfToken(): void
    {
        $client = static::createClient();
        $joke = $this->createJoke();
        $client->loginUser($this->createUser());

        $client->request('POST', '/joke/'.$joke->getId().'/explain', ['locale' => 'en'], [], ['HTTP_X_CSRF_TOKEN' => 'not-a-valid-token']);

        self::assertResponseStatusCodeSame(403);
    }

    public function testExplainIsRateLimited(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $joke = $this->createJoke();
        $client->loginUser($this->createUser());

        $completionProvider = $this->createMock(CompletionProviderInterface::class);
        // Only the very first POST actually reaches the provider - every request after it hits
        // the same joke+locale, which is cached from that first call.
        $completionProvider->expects($this->once())->method('complete')->willReturn('An explanation.');
        static::getContainer()->set(CompletionProviderInterface::class, $completionProvider);

        $crawler = $client->request('GET', '/joke/'.$joke->getId());
        $element = $crawler->filter('[data-explain-url-value]');
        $url = $element->attr('data-explain-url-value');
        $token = $element->attr('data-explain-token-value');

        // The limiter allows 10 requests per minute; exhaust it, then the next one must be rejected.
        for ($i = 0; $i < 10; ++$i) {
            $client->request('POST', $url, ['locale' => 'en'], [], ['HTTP_X_CSRF_TOKEN' => $token]);
            self::assertResponseIsSuccessful();
        }

        $client->request('POST', $url, ['locale' => 'en'], [], ['HTTP_X_CSRF_TOKEN' => $token]);
        self::assertResponseStatusCodeSame(429);
    }

    public function testExplainReturnsAFriendlyErrorWhenTheAiServiceIsDown(): void
    {
        // No CompletionProviderInterface mock is registered, so the test env's AI_PROVIDER=null
        // NullAiProvider throws - exercising the same fallback path a real outage would.
        $client = static::createClient();
        $joke = $this->createJoke();
        $client->loginUser($this->createUser());

        $crawler = $client->request('GET', '/joke/'.$joke->getId());
        $element = $crawler->filter('[data-explain-url-value]');
        $url = $element->attr('data-explain-url-value');
        $token = $element->attr('data-explain-token-value');

        $client->request('POST', $url, ['locale' => 'en'], [], ['HTTP_X_CSRF_TOKEN' => $token]);

        self::assertResponseStatusCodeSame(503);
        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertArrayHasKey('error', $data);

        // The page itself must stay usable after an AI outage.
        $client->request('GET', '/joke/'.$joke->getId());
        self::assertResponseIsSuccessful();
    }

    private function createJoke(): Joke
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $joke = (new Joke())->setJoke('Chuck Norris can explain recursion by explaining recursion.');
        $entityManager->persist($joke);
        $entityManager->flush();

        return $joke;
    }

    private function createUser(): User
    {
        $container = static::getContainer();

        $user = new User();
        $user->setEmail(sprintf('explainer-%s@example.com', bin2hex(random_bytes(4))));
        $user->setPassword($container->get(UserPasswordHasherInterface::class)->hashPassword($user, 'irrelevant'));

        $entityManager = $container->get(EntityManagerInterface::class);
        $entityManager->persist($user);
        $entityManager->flush();

        return $user;
    }
}
