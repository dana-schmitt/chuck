<?php

namespace App\Services;

use App\Entity\Joke;
use App\Exception\JokeFetchException;
use App\Repository\JokeRepository;

class JokeManager
{
    public function __construct(
        private readonly JokeFetcher $jokeFetcher,
        private readonly JokeRepository $repository,
    ) {
    }

    public function getJoke(): ?Joke
    {
        try {
            $randomJoke = $this->jokeFetcher->fetch();

            $joke = $this->repository->findOneByText($randomJoke);
            if ($joke === null) {
                $joke = new Joke();
                $joke->setJoke($randomJoke);
                $this->repository->addJoke($joke);
            }

            return $joke;
        } catch (JokeFetchException) {
            return $this->repository->findRandom();
        }
    }
}
