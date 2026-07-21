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
        $sql = 'SELECT j.id, j.joke, j.categories FROM joke j WHERE j.approved = 1 ORDER BY RAND() LIMIT 1';

        $rsm = new ResultSetMapping();
        $rsm->addEntityResult(Joke::class, 'j');
        $rsm->addFieldResult('j', 'id', 'id');
        $rsm->addFieldResult('j', 'joke', 'joke');
        $rsm->addFieldResult('j', 'categories', 'categories');

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
            ->join(JokeLike::class, 'l', 'ON', 'l.joke = j')
            ->andWhere('j.approved = true')
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

    /**
     * @return Joke[] most recently submitted first
     */
    public function findPendingSubmissions(): array
    {
        return $this->createQueryBuilder('j')
            ->andWhere('j.approved = false')
            ->orderBy('j.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Full-text search over approved jokes (see the joke_fulltext index).
     *
     * Uses BOOLEAN MODE rather than NATURAL LANGUAGE MODE: the latter silently drops any word
     * that appears in over 50% of rows, which is a real risk on a small/young jokes table (a
     * handful of common words would already cross that threshold) - boolean mode has no such
     * cutoff. Each word is required (+) and prefix-matched (*), so "debug" also finds "debugger".
     *
     * @return Joke[] most relevant first
     */
    public function search(string $query, int $limit = 20): array
    {
        $terms = preg_split('/\s+/', trim($query), -1, PREG_SPLIT_NO_EMPTY);
        if ($terms === []) {
            return [];
        }

        $booleanQuery = implode(' ', array_map(
            static fn (string $term) => '+'.preg_replace('/[+\-<>()~*"@]+/', '', $term).'*',
            $terms,
        ));

        $sql = 'SELECT j.id, j.joke, j.categories
                FROM joke j
                WHERE j.approved = 1 AND MATCH(j.joke) AGAINST (:query IN BOOLEAN MODE)
                ORDER BY MATCH(j.joke) AGAINST (:query IN BOOLEAN MODE) DESC
                LIMIT '.$limit;

        $rsm = new ResultSetMapping();
        $rsm->addEntityResult(Joke::class, 'j');
        $rsm->addFieldResult('j', 'id', 'id');
        $rsm->addFieldResult('j', 'joke', 'joke');
        $rsm->addFieldResult('j', 'categories', 'categories');

        return $this->getEntityManager()->createNativeQuery($sql, $rsm)
            ->setParameter('query', $booleanQuery)
            ->getResult();
    }
}
