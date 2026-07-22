<?php

namespace App\Services;

use App\Entity\Joke;
use App\Exception\JokeFetchException;
use App\Message\GenerateJokeEmbeddingMessage;
use App\Repository\JokeRepository;
use Symfony\Component\Messenger\MessageBusInterface;

class JokeManager
{
    public function __construct(
        private readonly JokeFetcher $jokeFetcher,
        private readonly JokeRepository $repository,
        private readonly MessageBusInterface $messageBus,
        private readonly float $popularJokeChance = 0.3,
    ) {
    }

    public function getJoke(?string $category = null): ?Joke
    {
        // The "surface a popular joke instead" bias isn't category-aware, so skip it
        // whenever the caller explicitly asked for a joke from a specific category.
        if ($category === null && $this->popularJokeChance > 0 && mt_rand() / mt_getrandmax() < $this->popularJokeChance) {
            $popular = $this->repository->findRandomPopular();
            if ($popular !== null) {
                return $popular;
            }
        }

        try {
            $fetched = $this->jokeFetcher->fetch($category);

            $joke = $this->repository->findOneByText($fetched->text);
            if ($joke === null) {
                $joke = new Joke();
                $joke->setJoke($fetched->text);
                $joke->setCategories($fetched->categories);
                $this->repository->addJoke($joke);
                $this->messageBus->dispatch(new GenerateJokeEmbeddingMessage($joke->getId()));
            }

            return $joke;
        } catch (JokeFetchException) {
            return $this->repository->findRandom();
        }
    }
}
