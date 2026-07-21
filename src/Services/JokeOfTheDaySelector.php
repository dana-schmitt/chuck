<?php

namespace App\Services;

use App\Entity\JokeOfTheDay;
use App\Repository\JokeOfTheDayRepository;
use App\Repository\JokeRepository;

class JokeOfTheDaySelector
{
    private const AVOID_REPEAT_DAYS = 30;

    public function __construct(
        private readonly JokeOfTheDayRepository $jokeOfTheDayRepository,
        private readonly JokeRepository $jokeRepository,
    ) {
    }

    public function selectForToday(): JokeOfTheDay
    {
        return $this->selectFor(new \DateTimeImmutable('today'));
    }

    public function selectFor(\DateTimeImmutable $date): JokeOfTheDay
    {
        $existing = $this->jokeOfTheDayRepository->findForDate($date);
        if ($existing !== null) {
            return $existing;
        }

        $recentJokeIds = $this->jokeOfTheDayRepository->findRecentJokeIds(self::AVOID_REPEAT_DAYS);
        // Fall back to allowing a repeat if every approved joke has been featured recently
        // (a small jokes table shouldn't leave "Joke of the Day" with nothing to show).
        $joke = $this->jokeRepository->findRandomExcluding($recentJokeIds) ?? $this->jokeRepository->findRandom();

        if ($joke === null) {
            throw new \RuntimeException('No approved jokes available to select a Joke of the Day.');
        }

        $jokeOfTheDay = new JokeOfTheDay($date, $joke);
        $this->jokeOfTheDayRepository->save($jokeOfTheDay);

        return $jokeOfTheDay;
    }
}
