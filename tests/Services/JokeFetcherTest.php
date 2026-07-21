<?php

namespace App\Tests\Services;

use App\Exception\JokeFetchException;
use App\Services\JokeFetcher;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class JokeFetcherTest extends TestCase
{
    public function testFetchReturnsJoke(): void
    {
        $client = new MockHttpClient([
            new MockResponse(json_encode(['value' => 'This is a Joke!', 'categories' => ['dev']])),
        ]);
        $jokeFetcher = new JokeFetcher($client);

        $result = $jokeFetcher->fetch();

        self::assertEquals('This is a Joke!', $result->text);
        self::assertEquals(['dev'], $result->categories);
    }

    public function testFetchDefaultsCategoriesToEmptyArrayWhenMissing(): void
    {
        $client = new MockHttpClient([
            new MockResponse(json_encode(['value' => 'This is a Joke!'])),
        ]);
        $jokeFetcher = new JokeFetcher($client);

        $result = $jokeFetcher->fetch();

        self::assertEquals([], $result->categories);
    }

    public function testFetchPassesCategoryAsQueryParameter(): void
    {
        $client = new MockHttpClient(function (string $method, string $url) {
            self::assertStringContainsString('category=dev', $url);

            return new MockResponse(json_encode(['value' => 'A dev joke', 'categories' => ['dev']]));
        });
        $jokeFetcher = new JokeFetcher($client);

        $result = $jokeFetcher->fetch('dev');

        self::assertEquals('A dev joke', $result->text);
    }

    public function testFetchThrowsOnTransportFailure(): void
    {
        $client = new MockHttpClient([
            new MockResponse([new TransportException("I'm broken!")]),
        ]);
        $jokeFetcher = new JokeFetcher($client);

        $this->expectException(JokeFetchException::class);
        $this->expectExceptionMessage('Could not fetch a joke from the jokes API.');

        $jokeFetcher->fetch();
    }

    public function testFetchThrowsOnNonSuccessStatusCode(): void
    {
        $client = new MockHttpClient([
            new MockResponse('Service Unavailable', ['http_code' => 503]),
        ]);
        $jokeFetcher = new JokeFetcher($client);

        $this->expectException(JokeFetchException::class);
        $this->expectExceptionMessage('Unexpected status code 503 from the jokes API.');

        $jokeFetcher->fetch();
    }
}
