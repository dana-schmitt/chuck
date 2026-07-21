<?php

namespace App\Tests\Services;

use App\Entity\Joke;
use App\Exception\JokeFetchException;
use App\Repository\JokeRepository;
use App\Services\JokeFetcher;
use App\Services\JokeManager;
use PHPUnit\Framework\TestCase;

class JokeManagerTest extends TestCase
{
    public function testGetExistingJokeReturnsStoredEntity(): void
    {
        $stored = (new Joke())->setJoke('This is a Joke!');

        $jokeFetcher = $this->createMock(JokeFetcher::class);
        $jokeFetcher->expects($this->once())->method('fetch')->willReturn('This is a Joke!');

        $jokeRepository = $this->createMock(JokeRepository::class);
        $jokeRepository->expects($this->once())->method('findOneByText')->with('This is a Joke!')->willReturn($stored);
        $jokeRepository->expects($this->never())->method('addJoke');
        $jokeRepository->expects($this->never())->method('findRandom');

        $manager = new JokeManager($jokeFetcher, $jokeRepository);

        $this->assertSame($stored, $manager->getJoke());
    }

    public function testGetNonExistingJokeIsSaved(): void
    {
        $jokeFetcher = $this->createMock(JokeFetcher::class);
        $jokeFetcher->expects($this->once())->method('fetch')->willReturn('This is a Joke!');

        $jokeRepository = $this->createMock(JokeRepository::class);
        $jokeRepository->expects($this->once())->method('findOneByText')->with('This is a Joke!')->willReturn(null);
        $jokeRepository->expects($this->once())->method('addJoke')->with((new Joke())->setJoke('This is a Joke!'));
        $jokeRepository->expects($this->never())->method('findRandom');

        $manager = new JokeManager($jokeFetcher, $jokeRepository);

        $result = $manager->getJoke();

        $this->assertInstanceOf(Joke::class, $result);
        $this->assertEquals('This is a Joke!', $result->getJoke());
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

        $manager = new JokeManager($jokeFetcher, $jokeRepository);

        $this->assertSame($fallback, $manager->getJoke());
    }
}
