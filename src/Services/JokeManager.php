<?php

namespace App\Services;

use App\Entity\Joke;
use App\Repository\JokeRepository;

class JokeManager
{
    public function __construct(
        private readonly JokeFetcher $jokeFetcher,
        private readonly JokeRepository $repository,
    ) {
    }

    public function getJoke(): string
    {
        try {
            $randomJoke = $this->jokeFetcher->fetch();

            $jokeExists = $this->repository->jokeExists($randomJoke);
            if (!$jokeExists) {
                $joke = new Joke();
                $joke->setJoke($randomJoke);
                $this->repository->addJoke($joke);
            }

            return $randomJoke;
        } catch (\Throwable $throwable) {
            return $this->repository->findRandom()?->getJoke() ?? 'Chuck Norris is too busy to tell a joke right now.';
        }
    }
}
