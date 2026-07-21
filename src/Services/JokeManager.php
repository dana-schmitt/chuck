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
        private readonly float $popularJokeChance = 0.3,
    ) {
    }

    public function getJoke(): ?Joke
    {
        if ($this->popularJokeChance > 0 && mt_rand() / mt_getrandmax() < $this->popularJokeChance) {
            $popular = $this->repository->findRandomPopular();
            if ($popular !== null) {
                return $popular;
            }
        }

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
