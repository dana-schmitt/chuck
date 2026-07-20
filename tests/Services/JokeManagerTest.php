<?php

namespace App\Tests\Services;

use App\Entity\Joke;
use App\Repository\JokeRepository;
use App\Services\JokeFetcher;
use App\Services\JokeManager;
use PHPUnit\Framework\TestCase;

class JokeManagerTest extends TestCase
{
    public function testGetExistingJoke(): void
    {
        $jokeFetcher = $this->createMock(JokeFetcher::class);
        $jokeFetcher->expects($this->once())->method('fetch')->willReturn('This is a Joke!');

        $jokeRepository = $this->createMock(JokeRepository::class);
        $jokeRepository->expects($this->once())->method('jokeExists')->willReturn(true);
        $jokeRepository->expects($this->never())->method('addJoke');
        $jokeRepository->expects($this->never())->method('findRandom');

        $manager = new JokeManager($jokeFetcher, $jokeRepository);

        $result = $manager->getJoke();

        $this->assertEquals('This is a Joke!', $result);
    }

    public function testGetNonExistingJokeIsSaved(): void
    {
        $jokeFetcher = $this->createMock(JokeFetcher::class);
        $jokeFetcher->expects($this->once())->method('fetch')->willReturn('This is a Joke!');

        $jokeRepository = $this->createMock(JokeRepository::class);
        $jokeRepository->expects($this->once())->method('jokeExists')->willReturn(false);
        $jokeRepository->expects($this->once())->method('addJoke')->with((new Joke())->setJoke('This is a Joke!'));
        $jokeRepository->expects($this->never())->method('findRandom');

        $manager = new JokeManager($jokeFetcher, $jokeRepository);

        $result = $manager->getJoke();

        $this->assertEquals('This is a Joke!', $result);
    }

    public function testGetJokeFromDatabase(): void
    {
        $jokeFetcher = $this->createMock(JokeFetcher::class);
        $jokeFetcher->expects($this->once())->method('fetch')->willThrowException(
            new \RuntimeException('Something went wrong!')
        );

        $jokeRepository = $this->createMock(JokeRepository::class);
        $jokeRepository->expects($this->never())->method('jokeExists');
        $jokeRepository->expects($this->never())->method('addJoke');
        $jokeRepository->expects($this->once())->method('findRandom')->willReturn((new Joke())->setJoke('This is a Joke!'));

        $manager = new JokeManager($jokeFetcher, $jokeRepository);

        $result = $manager->getJoke();

        $this->assertEquals('This is a Joke!', $result);
    }
}
