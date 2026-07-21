<?php

namespace App\Tests\Services;

use App\Entity\Joke;
use App\Exception\JokeFetchException;
use App\Repository\JokeRepository;
use App\Services\FetchedJoke;
use App\Services\JokeFetcher;
use App\Services\JokeManager;
use PHPUnit\Framework\TestCase;

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

        $manager = new JokeManager($jokeFetcher, $jokeRepository, popularJokeChance: 0.0);

        $this->assertSame($stored, $manager->getJoke());
    }

    public function testGetNonExistingJokeIsSavedWithCategories(): void
    {
        $jokeFetcher = $this->createMock(JokeFetcher::class);
        $jokeFetcher->expects($this->once())->method('fetch')->willReturn(new FetchedJoke('This is a Joke!', ['dev']));

        $jokeRepository = $this->createMock(JokeRepository::class);
        $jokeRepository->expects($this->once())->method('findOneByText')->with('This is a Joke!')->willReturn(null);
        $jokeRepository->expects($this->once())->method('addJoke')->with(
            $this->callback(static fn (Joke $joke) => $joke->getJoke() === 'This is a Joke!' && $joke->getCategories() === ['dev']),
        );
        $jokeRepository->expects($this->never())->method('findRandom');

        $manager = new JokeManager($jokeFetcher, $jokeRepository, popularJokeChance: 0.0);

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

        $manager = new JokeManager($jokeFetcher, $jokeRepository, popularJokeChance: 0.0);

        $this->assertSame($fallback, $manager->getJoke());
    }

    public function testAlwaysReturnsAPopularJokeWhenChanceIsCertain(): void
    {
        $popular = (new Joke())->setJoke('A crowd favorite');

        $jokeFetcher = $this->createMock(JokeFetcher::class);
        $jokeFetcher->expects($this->never())->method('fetch');

        $jokeRepository = $this->createMock(JokeRepository::class);
        $jokeRepository->expects($this->once())->method('findRandomPopular')->willReturn($popular);

        $manager = new JokeManager($jokeFetcher, $jokeRepository, popularJokeChance: 1.0);

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

        $manager = new JokeManager($jokeFetcher, $jokeRepository, popularJokeChance: 1.0);

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

        $manager = new JokeManager($jokeFetcher, $jokeRepository, popularJokeChance: 1.0);

        $this->assertSame($fetched, $manager->getJoke('dev'));
    }
}
