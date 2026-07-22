<?php

namespace App\Repository;

use App\Entity\Joke;
use App\Entity\JokeExplanation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<JokeExplanation>
 */
class JokeExplanationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, JokeExplanation::class);
    }

    public function findOneByJokeAndLocale(Joke $joke, string $locale): ?JokeExplanation
    {
        return $this->findOneBy(['joke' => $joke, 'locale' => $locale]);
    }

    public function save(JokeExplanation $explanation): void
    {
        $this->getEntityManager()->persist($explanation);
        $this->getEntityManager()->flush();
    }
}
