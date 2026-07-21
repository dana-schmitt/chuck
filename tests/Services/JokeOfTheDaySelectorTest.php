<?php

namespace App\Tests\Services;

use App\Entity\Joke;
use App\Entity\JokeOfTheDay;
use App\Repository\JokeOfTheDayRepository;
use App\Repository\JokeRepository;
use App\Services\JokeOfTheDaySelector;
use PHPUnit\Framework\TestCase;

class JokeOfTheDaySelectorTest extends TestCase
{
    public function testReusesTheExistingJokeOfTheDayForTheSameDate(): void
    {
        $date = new \DateTimeImmutable('2026-07-21');
        $existing = new JokeOfTheDay($date, (new Joke())->setJoke('Already picked'));

        $jokeOfTheDayRepository = $this->createMock(JokeOfTheDayRepository::class);
        $jokeOfTheDayRepository->expects($this->once())->method('findForDate')->with($date)->willReturn($existing);
        $jokeOfTheDayRepository->expects($this->never())->method('findRecentJokeIds');
        $jokeOfTheDayRepository->expects($this->never())->method('save');

        $jokeRepository = $this->createMock(JokeRepository::class);
        $jokeRepository->expects($this->never())->method('findRandomExcluding');

        $selector = new JokeOfTheDaySelector($jokeOfTheDayRepository, $jokeRepository);

        $this->assertSame($existing, $selector->selectFor($date));
    }

    public function testPicksARandomJokeExcludingRecentlyFeaturedOnesWhenNoneExistsYet(): void
    {
        $date = new \DateTimeImmutable('2026-07-21');
        $joke = (new Joke())->setJoke('Freshly picked');

        $jokeOfTheDayRepository = $this->createMock(JokeOfTheDayRepository::class);
        $jokeOfTheDayRepository->expects($this->once())->method('findForDate')->with($date)->willReturn(null);
        $jokeOfTheDayRepository->expects($this->once())->method('findRecentJokeIds')->with(30)->willReturn([1, 2, 3]);
        $jokeOfTheDayRepository->expects($this->once())->method('save')->with(
            $this->callback(static fn (JokeOfTheDay $jotd) => $jotd->getDate() == $date && $jotd->getJoke() === $joke),
        );

        $jokeRepository = $this->createMock(JokeRepository::class);
        $jokeRepository->expects($this->once())->method('findRandomExcluding')->with([1, 2, 3])->willReturn($joke);
        $jokeRepository->expects($this->never())->method('findRandom');

        $selector = new JokeOfTheDaySelector($jokeOfTheDayRepository, $jokeRepository);

        $result = $selector->selectFor($date);

        $this->assertSame($joke, $result->getJoke());
        $this->assertEquals($date, $result->getDate());
    }

    public function testFallsBackToAnyRandomJokeWhenEveryJokeWasRecentlyFeatured(): void
    {
        $date = new \DateTimeImmutable('2026-07-21');
        $joke = (new Joke())->setJoke('A repeat, but better than nothing');

        $jokeOfTheDayRepository = $this->createMock(JokeOfTheDayRepository::class);
        $jokeOfTheDayRepository->method('findForDate')->willReturn(null);
        $jokeOfTheDayRepository->method('findRecentJokeIds')->willReturn([1]);
        $jokeOfTheDayRepository->expects($this->once())->method('save');

        $jokeRepository = $this->createMock(JokeRepository::class);
        $jokeRepository->expects($this->once())->method('findRandomExcluding')->willReturn(null);
        $jokeRepository->expects($this->once())->method('findRandom')->willReturn($joke);

        $selector = new JokeOfTheDaySelector($jokeOfTheDayRepository, $jokeRepository);

        $this->assertSame($joke, $selector->selectFor($date)->getJoke());
    }

    public function testThrowsWhenNoApprovedJokesExistAtAll(): void
    {
        $jokeOfTheDayRepository = $this->createMock(JokeOfTheDayRepository::class);
        $jokeOfTheDayRepository->method('findForDate')->willReturn(null);
        $jokeOfTheDayRepository->method('findRecentJokeIds')->willReturn([]);
        $jokeOfTheDayRepository->expects($this->never())->method('save');

        $jokeRepository = $this->createMock(JokeRepository::class);
        $jokeRepository->method('findRandomExcluding')->willReturn(null);
        $jokeRepository->method('findRandom')->willReturn(null);

        $selector = new JokeOfTheDaySelector($jokeOfTheDayRepository, $jokeRepository);

        $this->expectException(\RuntimeException::class);
        $selector->selectForToday();
    }
}
