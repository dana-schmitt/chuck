<?php

namespace App\Tests\Ai;

use App\Ai\NullAiProvider;
use App\Exception\AiServiceException;
use PHPUnit\Framework\TestCase;

class NullAiProviderTest extends TestCase
{
    public function testEmbedAlwaysThrows(): void
    {
        $this->expectException(AiServiceException::class);

        (new NullAiProvider())->embed(['a joke']);
    }

    public function testCompleteAlwaysThrows(): void
    {
        $this->expectException(AiServiceException::class);

        (new NullAiProvider())->complete('system', 'user');
    }
}
