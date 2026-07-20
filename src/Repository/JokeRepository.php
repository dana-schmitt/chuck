<?php

namespace App\Repository;

use App\Entity\Joke;
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

    public function jokeExists(string $joke): bool
    {
        $qb = $this->createQueryBuilder('j');
        $qb
            ->select($qb->expr()->count('j.id'))
            ->where('j.joke = :joke')
            ->setParameter('joke', $joke)
        ;

        return $qb->getQuery()->getSingleScalarResult() > 0;
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

    //    /**
    //     * @return Joke[] Returns an array of Joke objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('j')
    //            ->andWhere('j.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('j.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Joke
    //    {
    //        return $this->createQueryBuilder('j')
    //            ->andWhere('j.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
