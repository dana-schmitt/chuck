<?php

namespace App\Ai;

use App\Exception\AiServiceException;

/**
 * Selected via AI_PROVIDER=null. Always behaves as if the AI service were
 * unreachable, so every caller exercises the same fallback path it would use
 * for a real outage - useful for local development without running
 * ai-service at all, and exercised directly in tests.
 */
final class NullAiProvider implements EmbeddingProviderInterface, CompletionProviderInterface
{
    public function embed(array $texts): array
    {
        throw new AiServiceException('The AI provider is disabled (AI_PROVIDER=null).');
    }

    public function complete(string $system, string $user, ?array $responseSchema = null, int $maxTokens = 500): string|array
    {
        throw new AiServiceException('The AI provider is disabled (AI_PROVIDER=null).');
    }
}
