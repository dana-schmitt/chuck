<?php

namespace App\Ai;

/**
 * Chooses the AI provider implementation from config (AI_PROVIDER), not code -
 * see config/services.yaml, where this factory backs both provider interfaces.
 */
final readonly class AiProviderFactory
{
    public function __construct(
        private HttpAiServiceProvider $httpProvider,
        private NullAiProvider $nullProvider,
        private string $aiProvider,
    ) {
    }

    public function createEmbeddingProvider(): EmbeddingProviderInterface
    {
        return $this->resolve();
    }

    public function createCompletionProvider(): CompletionProviderInterface
    {
        return $this->resolve();
    }

    private function resolve(): HttpAiServiceProvider|NullAiProvider
    {
        return $this->aiProvider === 'null' ? $this->nullProvider : $this->httpProvider;
    }
}
