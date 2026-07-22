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

    public function findRandom(?string $category = null): ?Joke
    {
        $sql = 'SELECT j.id, j.joke, j.categories FROM joke j WHERE j.approved = 1';
        if ($category !== null) {
            $sql .= ' AND JSON_CONTAINS(j.categories, :category)';
        }
        $sql .= ' ORDER BY RAND() LIMIT 1';

        $rsm = new ResultSetMapping();
        $rsm->addEntityResult(Joke::class, 'j');
        $rsm->addFieldResult('j', 'id', 'id');
        $rsm->addFieldResult('j', 'joke', 'joke');
        $rsm->addFieldResult('j', 'categories', 'categories');

        $query = $this->getEntityManager()->createNativeQuery($sql, $rsm);
        if ($category !== null) {
            $query->setParameter('category', json_encode($category));
        }

        return $query->getOneOrNullResult();
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

    /**
     * @return Joke[] most recent first, optionally filtered to a single category
     */
    public function findApproved(?string $category = null, int $limit = 20): array
    {
        if ($category === null) {
            return $this->findBy(['approved' => true], ['id' => 'DESC'], $limit);
        }

        $sql = 'SELECT j.id, j.joke, j.categories
                FROM joke j
                WHERE j.approved = 1 AND JSON_CONTAINS(j.categories, :category)
                ORDER BY j.id DESC
                LIMIT '.$limit;

        $rsm = new ResultSetMapping();
        $rsm->addEntityResult(Joke::class, 'j');
        $rsm->addFieldResult('j', 'id', 'id');
        $rsm->addFieldResult('j', 'joke', 'joke');
        $rsm->addFieldResult('j', 'categories', 'categories');

        return $this->getEntityManager()->createNativeQuery($sql, $rsm)
            ->setParameter('category', json_encode($category))
            ->getResult();
    }

    /**
     * @param int[] $ids
     *
     * @return Joke[] approved jokes matching the given ids, in the same order as $ids (unlike
     *                 findBy(), which doesn't guarantee any particular order) - missing/
     *                 unapproved ids are silently skipped
     */
    public function findByIdsPreservingOrder(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $jokesById = [];
        foreach ($this->findBy(['id' => $ids, 'approved' => true]) as $joke) {
            $jokesById[$joke->getId()] = $joke;
        }

        return array_values(array_filter(array_map(
            static fn (int $id) => $jokesById[$id] ?? null,
            $ids,
        )));
    }

    /**
     * @param int[] $excludeIds
     */
    public function findRandomExcluding(array $excludeIds = []): ?Joke
    {
        $where = 'j.approved = 1';
        if ($excludeIds !== []) {
            $placeholders = implode(',', array_fill(0, \count($excludeIds), '?'));
            $where .= " AND j.id NOT IN ({$placeholders})";
        }

        $sql = "SELECT j.id, j.joke, j.categories FROM joke j WHERE {$where} ORDER BY RAND() LIMIT 1";

        $rsm = new ResultSetMapping();
        $rsm->addEntityResult(Joke::class, 'j');
        $rsm->addFieldResult('j', 'id', 'id');
        $rsm->addFieldResult('j', 'joke', 'joke');
        $rsm->addFieldResult('j', 'categories', 'categories');

        $query = $this->getEntityManager()->createNativeQuery($sql, $rsm);
        foreach (array_values($excludeIds) as $i => $id) {
            $query->setParameter($i + 1, $id);
        }

        return $query->getOneOrNullResult();
    }
}
