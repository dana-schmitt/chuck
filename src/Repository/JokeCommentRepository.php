<?php

namespace App\Repository;

use App\Entity\Joke;
use App\Entity\JokeComment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<JokeComment>
 */
class JokeCommentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, JokeComment::class);
    }

    public function add(JokeComment $comment): void
    {
        $this->getEntityManager()->persist($comment);
        $this->getEntityManager()->flush();
    }

    public function remove(JokeComment $comment): void
    {
        $this->getEntityManager()->remove($comment);
        $this->getEntityManager()->flush();
    }

    /**
     * @return JokeComment[] oldest first, like a conversation
     */
    public function findByJoke(Joke $joke): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.joke = :joke')
            ->setParameter('joke', $joke)
            ->orderBy('c.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
