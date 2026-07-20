<?php

namespace App\Tests\Services;

use App\Entity\Joke;
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
            new MockResponse(json_encode(['value' => 'This is a Joke!'])),
        ]);
        $jokeFetcher = new JokeFetcher($client);

        $result = $jokeFetcher->fetch();

        self::assertEquals('This is a Joke!', $result);
    }

    public function testFetchReturnsNullOnFailure(): void
    {
        $client = new MockHttpClient([
            new MockResponse([new TransportException("I'm broken!")]),
        ]);
        $jokeFetcher = new JokeFetcher($client);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('FOOBAR');

        $jokeFetcher->fetch();
    }
}
