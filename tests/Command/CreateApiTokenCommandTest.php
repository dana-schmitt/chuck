<?php

namespace App\Tests\Command;

use App\Repository\ApiTokenRepository;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

class CreateApiTokenCommandTest extends KernelTestCase
{
    public function testCreatesATokenAndPrintsTheRawValueOnce(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        $application = new Application(self::$kernel);
        $command = $application->find('app:api-token:create');
        $tester = new CommandTester($command);
        $tester->execute(['label' => 'mcp-server']);

        $tester->assertCommandIsSuccessful();

        $output = $tester->getDisplay();
        self::assertMatchesRegularExpression('/Token \(shown once.*\):\s*([0-9a-f]{64})/s', $output, $output);
        preg_match('/Token \(shown once.*\):\s*([0-9a-f]{64})/s', $output, $matches);
        $rawToken = $matches[1];

        $apiTokenRepository = $container->get(ApiTokenRepository::class);
        $apiToken = $apiTokenRepository->findByRawToken($rawToken);

        self::assertNotNull($apiToken);
        self::assertSame('mcp-server', $apiToken->getLabel());
        self::assertNotSame($rawToken, $apiToken->getTokenHash(), 'The raw token must never be stored as-is.');
    }

    public function testEachTokenGetsAUniqueValue(): void
    {
        self::bootKernel();

        $application = new Application(self::$kernel);
        $command = $application->find('app:api-token:create');

        $firstTester = new CommandTester($command);
        $firstTester->execute(['label' => 'first']);
        preg_match('/Token \(shown once.*\):\s*([0-9a-f]{64})/s', $firstTester->getDisplay(), $firstMatches);

        $secondTester = new CommandTester($command);
        $secondTester->execute(['label' => 'second']);
        preg_match('/Token \(shown once.*\):\s*([0-9a-f]{64})/s', $secondTester->getDisplay(), $secondMatches);

        self::assertNotSame($firstMatches[1], $secondMatches[1]);
    }
}
