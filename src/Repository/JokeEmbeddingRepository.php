<?php

namespace App\Repository;

use App\Entity\Joke;
use App\Entity\JokeEmbedding;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<JokeEmbedding>
 */
class JokeEmbeddingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, JokeEmbedding::class);
    }

    public function findOneByJoke(Joke $joke): ?JokeEmbedding
    {
        return $this->findOneBy(['joke' => $joke]);
    }

    public function save(JokeEmbedding $embedding): void
    {
        $this->getEntityManager()->persist($embedding);
        $this->getEntityManager()->flush();
    }

    /**
     * @return Joke[] approved jokes that don't have an embedding yet, oldest first
     */
    public function findApprovedJokesWithoutEmbedding(?int $limit = null): array
    {
        $queryBuilder = $this->getEntityManager()->createQueryBuilder()
            ->select('j')
            ->from(Joke::class, 'j')
            ->leftJoin(JokeEmbedding::class, 'e', 'WITH', 'e.joke = j')
            ->andWhere('j.approved = true')
            ->andWhere('e.id IS NULL')
            ->orderBy('j.id', 'ASC');

        if ($limit !== null) {
            $queryBuilder->setMaxResults($limit);
        }

        return $queryBuilder->getQuery()->getResult();
    }

    /**
     * @return array<int, float[]> vectors keyed by joke id, for jokes belonging to approved jokes only
     */
    public function findAllVectorsByJokeId(): array
    {
        $rows = $this->createQueryBuilder('e')
            ->select('IDENTITY(e.joke) AS jokeId', 'e.vector AS vector')
            ->innerJoin(Joke::class, 'j', 'WITH', 'j = e.joke')
            ->andWhere('j.approved = true')
            ->getQuery()
            ->getResult();

        $vectors = [];
        foreach ($rows as $row) {
            $vectors[(int) $row['jokeId']] = $row['vector'];
        }

        return $vectors;
    }
}
