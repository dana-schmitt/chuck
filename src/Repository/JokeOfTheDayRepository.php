<?php

namespace App\Repository;

use App\Entity\JokeOfTheDay;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<JokeOfTheDay>
 */
class JokeOfTheDayRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, JokeOfTheDay::class);
    }

    public function findForDate(\DateTimeImmutable $date): ?JokeOfTheDay
    {
        return $this->findOneBy(['date' => $date]);
    }

    /**
     * @return int[] joke IDs featured in the last $days days, to help avoid immediate repeats
     */
    public function findRecentJokeIds(int $days): array
    {
        $since = new \DateTimeImmutable(sprintf('-%d days', $days));

        $rows = $this->createQueryBuilder('jotd')
            ->select('IDENTITY(jotd.joke) AS jokeId')
            ->andWhere('jotd.date >= :since')
            ->setParameter('since', $since)
            ->getQuery()
            ->getScalarResult();

        return array_map(static fn (array $row) => (int) $row['jokeId'], $rows);
    }

    public function save(JokeOfTheDay $jokeOfTheDay): void
    {
        $this->getEntityManager()->persist($jokeOfTheDay);
        $this->getEntityManager()->flush();
    }
}
