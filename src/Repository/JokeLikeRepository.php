<?php

namespace App\Repository;

use App\Entity\Joke;
use App\Entity\JokeLike;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<JokeLike>
 */
class JokeLikeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, JokeLike::class);
    }

    public function findOneByUserAndJoke(User $user, Joke $joke): ?JokeLike
    {
        return $this->findOneBy(['user' => $user, 'joke' => $joke]);
    }

    public function isLikedBy(User $user, Joke $joke): bool
    {
        return $this->findOneByUserAndJoke($user, $joke) !== null;
    }

    /**
     * @return JokeLike[] most recently liked first
     */
    public function findRecentByUser(User $user): array
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.user = :user')
            ->setParameter('user', $user)
            ->orderBy('l.likedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Toggles the like for the given user/joke pair and returns the new liked state.
     */
    public function toggle(User $user, Joke $joke): bool
    {
        $entityManager = $this->getEntityManager();
        $existing = $this->findOneByUserAndJoke($user, $joke);

        if ($existing !== null) {
            $entityManager->remove($existing);
            $entityManager->flush();

            return false;
        }

        $entityManager->persist(new JokeLike($user, $joke));
        $entityManager->flush();

        return true;
    }
}
