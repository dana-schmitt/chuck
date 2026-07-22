<?php

namespace App\Ai;

use App\Exception\AiServiceException;

interface EmbeddingProviderInterface
{
    /**
     * @param string[] $texts
     *
     * @return array<int, float[]> one vector per input text, in the same order
     *
     * @throws AiServiceException if the provider is unavailable or the call fails
     */
    public function embed(array $texts): array;
}
