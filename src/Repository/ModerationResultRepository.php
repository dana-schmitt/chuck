<?php

namespace App\Repository;

use App\Entity\Joke;
use App\Entity\ModerationResult;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ModerationResult>
 */
class ModerationResultRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ModerationResult::class);
    }

    public function findOneByJoke(Joke $joke): ?ModerationResult
    {
        return $this->findOneBy(['joke' => $joke]);
    }

    public function save(ModerationResult $result): void
    {
        $this->getEntityManager()->persist($result);
        $this->getEntityManager()->flush();
    }

    /**
     * @param Joke[] $jokes
     *
     * @return array<int, ModerationResult> keyed by joke id
     */
    public function findByJokesIndexedByJokeId(array $jokes): array
    {
        if ($jokes === []) {
            return [];
        }

        $results = $this->createQueryBuilder('m')
            ->andWhere('m.joke IN (:jokes)')
            ->setParameter('jokes', $jokes)
            ->getQuery()
            ->getResult();

        $byJokeId = [];
        foreach ($results as $result) {
            $byJokeId[$result->getJoke()->getId()] = $result;
        }

        return $byJokeId;
    }
}
