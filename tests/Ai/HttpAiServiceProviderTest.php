<?php

namespace App\Tests\Ai;

use App\Ai\HttpAiServiceProvider;
use App\Exception\AiServiceException;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class HttpAiServiceProviderTest extends TestCase
{
    public function testEmbedReturnsVectorsFromTheService(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse(json_encode([
                'vectors' => [[0.1, 0.2], [0.3, 0.4]],
                'model' => 'text-embedding-3-small',
            ])),
        ]);

        $provider = new HttpAiServiceProvider($httpClient, new NullLogger());

        self::assertSame([[0.1, 0.2], [0.3, 0.4]], $provider->embed(['joke one', 'joke two']));
    }

    public function testEmbedThrowsOnServiceFailure(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse('', ['http_code' => 503]),
        ]);

        $provider = new HttpAiServiceProvider($httpClient, new NullLogger());

        $this->expectException(AiServiceException::class);
        $provider->embed(['joke one']);
    }

    public function testCompleteWithoutSchemaReturnsPlainText(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse(json_encode([
                'text' => 'This is a fake explanation.',
                'data' => null,
                'model' => 'gpt-4o-mini',
            ])),
        ]);

        $provider = new HttpAiServiceProvider($httpClient, new NullLogger());

        self::assertSame('This is a fake explanation.', $provider->complete('system prompt', 'user text'));
    }

    public function testCompleteWithSchemaReturnsStructuredData(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse(json_encode([
                'text' => null,
                'data' => ['recommendation' => 'approve', 'confidence' => 0.9],
                'model' => 'gpt-4o-mini',
            ])),
        ]);

        $provider = new HttpAiServiceProvider($httpClient, new NullLogger());

        $schema = ['type' => 'object', 'properties' => ['recommendation' => ['type' => 'string']]];
        self::assertSame(
            ['recommendation' => 'approve', 'confidence' => 0.9],
            $provider->complete('system prompt', 'user text', $schema),
        );
    }

    public function testCompleteThrowsOnServiceFailure(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse('', ['http_code' => 502]),
        ]);

        $provider = new HttpAiServiceProvider($httpClient, new NullLogger());

        $this->expectException(AiServiceException::class);
        $provider->complete('system prompt', 'user text');
    }
}
