<?php

namespace App\Ai;

use App\Exception\AiServiceException;

interface CompletionProviderInterface
{
    /**
     * @param array<string, mixed>|null $responseSchema when given, the AI service validates its
     *                                                   own answer against this JSON schema before
     *                                                   returning it - the returned array is
     *                                                   guaranteed to match it
     *
     * @return string|array<string, mixed> plain text when $responseSchema is null, a schema-matching
     *                                      array otherwise
     *
     * @throws AiServiceException if the provider is unavailable or the call fails
     */
    public function complete(string $system, string $user, ?array $responseSchema = null, int $maxTokens = 500): string|array;
}
