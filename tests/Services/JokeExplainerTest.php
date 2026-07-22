<?php

namespace App\Tests\Services;

use App\Ai\CompletionProviderInterface;
use App\Entity\Joke;
use App\Entity\JokeExplanation;
use App\Exception\AiServiceException;
use App\Repository\JokeExplanationRepository;
use App\Services\JokeExplainer;
use PHPUnit\Framework\TestCase;

class JokeExplainerTest extends TestCase
{
    public function testGeneratesAndPersistsAnExplanationWhenNoneIsCachedYet(): void
    {
        $joke = $this->jokeWithId(1, 'A wordplay joke');

        $repository = $this->createMock(JokeExplanationRepository::class);
        $repository->expects($this->once())->method('findOneByJokeAndLocale')->with($joke, 'en')->willReturn(null);
        $repository->expects($this->once())->method('save')->with($this->isInstanceOf(JokeExplanation::class));

        $completionProvider = $this->createMock(CompletionProviderInterface::class);
        $completionProvider->expects($this->once())->method('complete')->willReturn('This plays on the phrase...');

        $explainer = new JokeExplainer($completionProvider, $repository);

        $explanation = $explainer->explain($joke, 'en');

        self::assertSame('This plays on the phrase...', $explanation->getExplanation());
        self::assertSame('en', $explanation->getLocale());
        self::assertSame($joke, $explanation->getJoke());
    }

    public function testReturnsTheCachedExplanationWithoutCallingTheLlmAgain(): void
    {
        $joke = $this->jokeWithId(1, 'A wordplay joke');
        $existing = new JokeExplanation($joke, 'de', 'Das ist ein Wortspiel...');

        $repository = $this->createMock(JokeExplanationRepository::class);
        $repository->method('findOneByJokeAndLocale')->with($joke, 'de')->willReturn($existing);
        $repository->expects($this->never())->method('save');

        $completionProvider = $this->createMock(CompletionProviderInterface::class);
        $completionProvider->expects($this->never())->method('complete');

        $explainer = new JokeExplainer($completionProvider, $repository);

        $explanation = $explainer->explain($joke, 'de');

        self::assertSame($existing, $explanation);
    }

    public function testFallsBackToEnglishForAnUnsupportedLocale(): void
    {
        $joke = $this->jokeWithId(1, 'A wordplay joke');

        $repository = $this->createMock(JokeExplanationRepository::class);
        $repository->expects($this->once())->method('findOneByJokeAndLocale')->with($joke, 'en')->willReturn(null);
        $repository->method('save');

        $completionProvider = $this->createMock(CompletionProviderInterface::class);
        $completionProvider->method('complete')->willReturn('Explanation.');

        $explainer = new JokeExplainer($completionProvider, $repository);

        $explanation = $explainer->explain($joke, 'fr');

        self::assertSame('en', $explanation->getLocale());
    }

    public function testPropagatesAiServiceExceptionsFromTheCompletionCall(): void
    {
        $joke = $this->jokeWithId(1, 'A wordplay joke');

        $repository = $this->createMock(JokeExplanationRepository::class);
        $repository->method('findOneByJokeAndLocale')->willReturn(null);
        $repository->expects($this->never())->method('save');

        $completionProvider = $this->createMock(CompletionProviderInterface::class);
        $completionProvider->method('complete')->willThrowException(new AiServiceException('down'));

        $explainer = new JokeExplainer($completionProvider, $repository);

        $this->expectException(AiServiceException::class);
        $explainer->explain($joke, 'en');
    }

    private function jokeWithId(int $id, string $text): Joke
    {
        $joke = (new Joke())->setJoke($text);
        (new \ReflectionProperty(Joke::class, 'id'))->setValue($joke, $id);

        return $joke;
    }
}
