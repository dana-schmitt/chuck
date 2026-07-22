<?php

namespace App\Tests\Ai;

use App\Ai\AiProviderFactory;
use App\Ai\HttpAiServiceProvider;
use App\Ai\NullAiProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;

class AiProviderFactoryTest extends TestCase
{
    public function testDefaultsToTheHttpProvider(): void
    {
        $factory = new AiProviderFactory($this->httpProvider(), new NullAiProvider(), 'http');

        self::assertInstanceOf(HttpAiServiceProvider::class, $factory->createEmbeddingProvider());
        self::assertInstanceOf(HttpAiServiceProvider::class, $factory->createCompletionProvider());
    }

    public function testUsesTheNullProviderWhenConfigured(): void
    {
        $factory = new AiProviderFactory($this->httpProvider(), new NullAiProvider(), 'null');

        self::assertInstanceOf(NullAiProvider::class, $factory->createEmbeddingProvider());
        self::assertInstanceOf(NullAiProvider::class, $factory->createCompletionProvider());
    }

    private function httpProvider(): HttpAiServiceProvider
    {
        return new HttpAiServiceProvider(new MockHttpClient(), new NullLogger());
    }
}
