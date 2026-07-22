<?php

namespace App\Tests\Controller\Api;

use App\Entity\ApiToken;
use App\Entity\Joke;
use App\Repository\ApiTokenRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ApiTokenAuthTest extends WebTestCase
{
    public function testValidBearerTokenGrantsAccessWithoutASession(): void
    {
        $client = static::createClient();
        $this->createJoke();
        $rawToken = $this->createApiToken('mcp-server');

        $client->request('GET', '/api/jokes', [], [], ['HTTP_AUTHORIZATION' => 'Bearer '.$rawToken]);

        self::assertResponseIsSuccessful();
    }

    public function testInvalidBearerTokenIsRejectedWithA401(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/jokes', [], [], ['HTTP_AUTHORIZATION' => 'Bearer not-a-real-token']);

        self::assertResponseStatusCodeSame(401);
        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertArrayHasKey('error', $data);
    }

    public function testMissingCredentialsStillRedirectToLoginLikeABrowserRequest(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/jokes');

        self::assertResponseRedirects('/login');
    }

    public function testApiTokenAuthenticatorDoesNotActivateOutsideApiRoutes(): void
    {
        // ApiTokenAuthenticator::supports() is scoped to /api - a Bearer header on any other
        // route is simply ignored, so it falls through to the normal anonymous-access behavior
        // (redirect to /login), never to an authenticated-but-forbidden 403.
        $client = static::createClient();
        $rawToken = $this->createApiToken('mcp-server');

        $client->request('GET', '/admin/jokes', [], [], ['HTTP_AUTHORIZATION' => 'Bearer '.$rawToken]);

        self::assertResponseRedirects('/login');
    }

    private function createApiToken(string $label): string
    {
        $rawToken = bin2hex(random_bytes(32));

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->persist(new ApiToken(ApiTokenRepository::hash($rawToken), $label));
        $entityManager->flush();

        return $rawToken;
    }

    private function createJoke(): Joke
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $joke = (new Joke())->setJoke('A joke reachable via API token '.bin2hex(random_bytes(4)));
        $entityManager->persist($joke);
        $entityManager->flush();

        return $joke;
    }
}
