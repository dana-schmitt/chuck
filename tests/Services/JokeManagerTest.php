<?php

namespace App\Tests\Services;

use App\Entity\Joke;
use App\Exception\JokeFetchException;
use App\Message\GenerateJokeEmbeddingMessage;
use App\Repository\JokeRepository;
use App\Services\FetchedJoke;
use App\Services\JokeFetcher;
use App\Services\JokeManager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

class JokeManagerTest extends TestCase
{
    public function testGetExistingJokeReturnsStoredEntity(): void
    {
        $stored = (new Joke())->setJoke('This is a Joke!');

        $jokeFetcher = $this->createMock(JokeFetcher::class);
        $jokeFetcher->expects($this->once())->method('fetch')->with(null)->willReturn(new FetchedJoke('This is a Joke!'));

        $jokeRepository = $this->createMock(JokeRepository::class);
        $jokeRepository->expects($this->once())->method('findOneByText')->with('This is a Joke!')->willReturn($stored);
        $jokeRepository->expects($this->never())->method('addJoke');
        $jokeRepository->expects($this->never())->method('findRandom');

        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects($this->never())->method('dispatch');

        $manager = new JokeManager($jokeFetcher, $jokeRepository, $messageBus, popularJokeChance: 0.0);

        $this->assertSame($stored, $manager->getJoke());
    }

    public function testGetNonExistingJokeIsSavedWithCategoriesAndDispatchesAnEmbeddingMessage(): void
    {
        $jokeFetcher = $this->createMock(JokeFetcher::class);
        $jokeFetcher->expects($this->once())->method('fetch')->willReturn(new FetchedJoke('This is a Joke!', ['dev']));

        $jokeRepository = $this->createMock(JokeRepository::class);
        $jokeRepository->expects($this->once())->method('findOneByText')->with('This is a Joke!')->willReturn(null);
        $jokeRepository->expects($this->once())->method('addJoke')->with(
            $this->callback(function (Joke $joke) {
                // Simulates the id a real flush() would assign, since JokeManager dispatches
                // the embedding message with it right after calling addJoke().
                (new \ReflectionProperty(Joke::class, 'id'))->setValue($joke, 1);

                return $joke->getJoke() === 'This is a Joke!' && $joke->getCategories() === ['dev'];
            }),
        );
        $jokeRepository->expects($this->never())->method('findRandom');

        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects($this->once())->method('dispatch')
            ->with($this->isInstanceOf(GenerateJokeEmbeddingMessage::class))
            ->willReturn(new Envelope(new GenerateJokeEmbeddingMessage(1)));

        $manager = new JokeManager($jokeFetcher, $jokeRepository, $messageBus, popularJokeChance: 0.0);

        $result = $manager->getJoke();

        $this->assertInstanceOf(Joke::class, $result);
        $this->assertEquals('This is a Joke!', $result->getJoke());
        $this->assertEquals(['dev'], $result->getCategories());
    }

    public function testGetJokeFromDatabaseOnFetchFailure(): void
    {
        $fallback = (new Joke())->setJoke('This is a Joke!');

        $jokeFetcher = $this->createMock(JokeFetcher::class);
        $jokeFetcher->expects($this->once())->method('fetch')->willThrowException(
            new JokeFetchException('Something went wrong!')
        );

        $jokeRepository = $this->createMock(JokeRepository::class);
        $jokeRepository->expects($this->never())->method('findOneByText');
        $jokeRepository->expects($this->never())->method('addJoke');
        $jokeRepository->expects($this->once())->method('findRandom')->willReturn($fallback);

        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects($this->never())->method('dispatch');

        $manager = new JokeManager($jokeFetcher, $jokeRepository, $messageBus, popularJokeChance: 0.0);

        $this->assertSame($fallback, $manager->getJoke());
    }

    public function testAlwaysReturnsAPopularJokeWhenChanceIsCertain(): void
    {
        $popular = (new Joke())->setJoke('A crowd favorite');

        $jokeFetcher = $this->createMock(JokeFetcher::class);
        $jokeFetcher->expects($this->never())->method('fetch');

        $jokeRepository = $this->createMock(JokeRepository::class);
        $jokeRepository->expects($this->once())->method('findRandomPopular')->willReturn($popular);

        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects($this->never())->method('dispatch');

        $manager = new JokeManager($jokeFetcher, $jokeRepository, $messageBus, popularJokeChance: 1.0);

        $this->assertSame($popular, $manager->getJoke());
    }

    public function testFallsBackToFetchingWhenNoPopularJokeExistsYet(): void
    {
        $fetched = (new Joke())->setJoke('This is a Joke!');

        $jokeFetcher = $this->createMock(JokeFetcher::class);
        $jokeFetcher->expects($this->once())->method('fetch')->willReturn(new FetchedJoke('This is a Joke!'));

        $jokeRepository = $this->createMock(JokeRepository::class);
        $jokeRepository->expects($this->once())->method('findRandomPopular')->willReturn(null);
        $jokeRepository->expects($this->once())->method('findOneByText')->with('This is a Joke!')->willReturn($fetched);

        $messageBus = $this->createMock(MessageBusInterface::class);

        $manager = new JokeManager($jokeFetcher, $jokeRepository, $messageBus, popularJokeChance: 1.0);

        $this->assertSame($fetched, $manager->getJoke());
    }

    public function testSkipsPopularJokeBiasWhenCategoryIsRequested(): void
    {
        $fetched = (new Joke())->setJoke('A dev joke');

        $jokeFetcher = $this->createMock(JokeFetcher::class);
        $jokeFetcher->expects($this->once())->method('fetch')->with('dev')->willReturn(new FetchedJoke('A dev joke', ['dev']));

        $jokeRepository = $this->createMock(JokeRepository::class);
        $jokeRepository->expects($this->never())->method('findRandomPopular');
        $jokeRepository->expects($this->once())->method('findOneByText')->willReturn($fetched);

        $messageBus = $this->createMock(MessageBusInterface::class);

        $manager = new JokeManager($jokeFetcher, $jokeRepository, $messageBus, popularJokeChance: 1.0);

        $this->assertSame($fetched, $manager->getJoke('dev'));
    }
}
