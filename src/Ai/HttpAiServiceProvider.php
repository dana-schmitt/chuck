<?php

namespace App\Ai;

use App\Exception\AiServiceException;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpClientExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Talks to the internal Python "ai-service" over HTTP (see ai-service/ and
 * config/packages/framework.yaml's ai_service.client scoped client).
 */
final readonly class HttpAiServiceProvider implements EmbeddingProviderInterface, CompletionProviderInterface
{
    public function __construct(
        #[Autowire(service: 'ai_service.client')]
        private HttpClientInterface $httpClient,
        #[Autowire(service: 'monolog.logger.ai')]
        private LoggerInterface $logger,
    ) {
    }

    public function embed(array $texts): array
    {
        $startedAt = microtime(true);

        try {
            $response = $this->httpClient->request('POST', '/embeddings', [
                'json' => ['texts' => $texts],
            ]);
            $data = $response->toArray();
        } catch (HttpClientExceptionInterface $exception) {
            $this->logger->error('AI embeddings call failed', [
                'duration_ms' => $this->elapsedMs($startedAt),
                'error' => $exception->getMessage(),
            ]);

            throw new AiServiceException('Could not fetch embeddings from the AI service.', previous: $exception);
        }

        $this->logger->info('AI embeddings call succeeded', [
            'duration_ms' => $this->elapsedMs($startedAt),
            'model' => $data['model'] ?? null,
            'count' => \count($texts),
        ]);

        return $data['vectors'];
    }

    public function complete(string $system, string $user, ?array $responseSchema = null, int $maxTokens = 500): string|array
    {
        $startedAt = microtime(true);

        try {
            $response = $this->httpClient->request('POST', '/complete', [
                'json' => [
                    'system' => $system,
                    'user' => $user,
                    'response_schema' => $responseSchema,
                    'max_tokens' => $maxTokens,
                ],
            ]);
            $data = $response->toArray();
        } catch (HttpClientExceptionInterface $exception) {
            $this->logger->error('AI completion call failed', [
                'duration_ms' => $this->elapsedMs($startedAt),
                'error' => $exception->getMessage(),
            ]);

            throw new AiServiceException('Could not get a completion from the AI service.', previous: $exception);
        }

        $this->logger->info('AI completion call succeeded', [
            'duration_ms' => $this->elapsedMs($startedAt),
            'model' => $data['model'] ?? null,
            'structured' => $responseSchema !== null,
        ]);

        return $responseSchema !== null ? $data['data'] : $data['text'];
    }

    private function elapsedMs(float $startedAt): int
    {
        return (int) round((microtime(true) - $startedAt) * 1000);
    }
}
