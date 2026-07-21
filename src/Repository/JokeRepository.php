<?php

namespace App\Repository;

use App\Entity\Joke;
use App\Entity\JokeLike;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Query\ResultSetMapping;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Joke>
 *
 * @method Joke|null find($id, $lockMode = null, $lockVersion = null)
 * @method Joke|null findOneBy(array $criteria, array $orderBy = null)
 * @method Joke[]    findAll()
 * @method Joke[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class JokeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Joke::class);
    }

    public function findOneByText(string $joke): ?Joke
    {
        return $this->findOneBy(['joke' => $joke]);
    }

    public function findRandom(): ?Joke
    {
        $sql = 'SELECT j.id, j.joke FROM joke j ORDER BY RAND() LIMIT 1';

        $rsm = new ResultSetMapping();
        $rsm->addEntityResult(Joke::class, 'j');
        $rsm->addFieldResult('j', 'id', 'id');
        $rsm->addFieldResult('j', 'joke', 'joke');

        return $this->getEntityManager()->createNativeQuery($sql, $rsm)->getOneOrNullResult();
    }

    public function addJoke(Joke $joke): void
    {
        $this->getEntityManager()->persist($joke);
        $this->getEntityManager()->flush();
    }

    /**
     * @return array<int, array{joke: Joke, likeCount: int}> most-liked jokes first
     */
    public function findTopLiked(int $limit = 10): array
    {
        $rows = $this->createQueryBuilder('j')
            ->select('j AS joke', 'COUNT(l.id) AS likeCount')
            ->join(JokeLike::class, 'l', 'WITH', 'l.joke = j')
            ->groupBy('j.id')
            ->orderBy('likeCount', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return array_map(
            static fn (array $row) => ['joke' => $row['joke'], 'likeCount' => (int) $row['likeCount']],
            $rows,
        );
    }

    /**
     * Picks one joke at random from the most-liked pool, for "Chuck me" to occasionally
     * surface a crowd favorite instead of a purely random/freshly fetched joke.
     */
    public function findRandomPopular(int $poolSize = 20): ?Joke
    {
        $topLiked = $this->findTopLiked($poolSize);
        if ($topLiked === []) {
            return null;
        }

        return $topLiked[array_rand($topLiked)]['joke'];
    }
}
